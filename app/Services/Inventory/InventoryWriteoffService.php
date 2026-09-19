<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\InventoryWriteoff;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Closing\BusinessDayService;
use App\Services\Documents\DocumentNumberService;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryWriteoffService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly InventoryService $inventory,
        private readonly InventoryCostService $costing,
        private readonly BusinessDayService $days,
        private readonly AuditLogger $audit,
    ) {
    }

    public function post(array $data, User $actor): InventoryWriteoff
    {
        if (! $actor->hasPermission('inventory.writeoff')) {
            throw new DomainException('The user is not allowed to post inventory write-offs.');
        }

        return DB::transaction(function () use ($data, $actor): InventoryWriteoff {
            if ($existing = InventoryWriteoff::query()
                ->with(['items.product', 'items.batch', 'items.stockMovement'])
                ->where('idempotency_key', $data['idempotency_key'])
                ->first()) {
                $this->assertRetryMatches($existing, $data);

                return $existing;
            }

            $this->days->lockOpen(now());

            $type = (string) ($data['writeoff_type'] ?? '');

            if (! in_array($type, ['damage', 'expiry'], true)) {
                throw new DomainException('Inventory write-off type must be damage or expiry.');
            }

            $reason = trim((string) ($data['reason'] ?? ''));

            if ($reason === '') {
                throw new DomainException('A reason is required for inventory write-off.');
            }

            $prepared = $this->prepareTargets($data['items'] ?? [], $type);

            if ($prepared->isEmpty()) {
                throw new DomainException('An inventory write-off requires at least one item.');
            }

            $writeoff = InventoryWriteoff::create([
                'number' => $this->numbers->next('inventory_writeoff', $type === 'damage' ? 'DMG' : 'EXP'),
                'idempotency_key' => $data['idempotency_key'],
                'writeoff_type' => $type,
                'posted_by_user_id' => $actor->id,
                'reason' => $reason,
                'total_cost' => '0.0000',
                'posted_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $totalCost = '0.0000';

            foreach ($prepared as $line) {
                /** @var Product $product */
                $product = Product::query()->lockForUpdate()->findOrFail($line['product']->id);
                $batch = $line['batch']
                    ? ProductBatch::query()->lockForUpdate()->findOrFail($line['batch']->id)
                    : null;

                $available = $batch
                    ? Decimal::normalize($batch->stock_on_hand)
                    : Decimal::normalize($product->stock_on_hand);

                if (Decimal::compare($line['quantity'], $available) > 0) {
                    throw new DomainException('Inventory write-off quantity exceeds available physical stock.');
                }

                if ($type === 'expiry') {
                    if (! $batch || ! $batch->expires_at || ! $batch->expires_at->lt(today())) {
                        throw new DomainException('Expiry write-off requires a batch that is already expired.');
                    }
                }

                $movement = $this->inventory->recordMovement(
                    product: $product,
                    type: $type === 'damage' ? StockMovementType::Damage : StockMovementType::Expiry,
                    baseQuantity: '-'.$line['quantity'],
                    batch: $batch,
                    actor: $actor,
                    notes: ucfirst($type).' write-off '.$writeoff->number.': '.$reason,
                    referenceType: InventoryWriteoff::class,
                    referenceId: $writeoff->id,
                    idempotencyKey: 'inventory-writeoff:'.$writeoff->id.':'.$product->id.':'.($batch?->id ?? 'none'),
                );

                $costAmount = $this->costing->consumeForAdjustment(
                    movement: $movement,
                    product: $product,
                    quantityBase: $line['quantity'],
                    batch: $batch,
                );

                $writeoff->items()->create([
                    'product_id' => $product->id,
                    'product_batch_id' => $batch?->id,
                    'stock_movement_id' => $movement->id,
                    'quantity_base' => $line['quantity'],
                    'cost_amount' => $costAmount,
                ]);

                $totalCost = Decimal::add($totalCost, $costAmount, 4);
            }

            $writeoff->forceFill(['total_cost' => $totalCost])->saveQuietly();

            $this->audit->record(
                'inventory.writeoff.posted',
                model: $writeoff,
                newValues: [
                    'number' => $writeoff->number,
                    'writeoff_type' => $type,
                    'total_cost' => $totalCost,
                    'line_count' => $prepared->count(),
                ],
                actor: $actor,
            );

            return $writeoff->fresh([
                'items.product',
                'items.batch',
                'items.stockMovement',
                'postedBy',
            ]);
        });
    }

    private function prepareTargets(array $items, string $type): Collection
    {
        $prepared = collect();
        $seen = [];

        foreach ($items as $input) {
            [$productId, $batchId] = $this->parseTarget((string) ($input['target'] ?? ''));
            $key = $productId.':'.($batchId ?? '');

            if (isset($seen[$key])) {
                throw new DomainException('An inventory write-off cannot contain the same product/batch twice.');
            }

            $seen[$key] = true;
            $product = Product::query()->whereKey($productId)->where('track_stock', true)->first();

            if (! $product) {
                throw new DomainException('The selected inventory write-off product is unavailable.');
            }

            $batch = null;

            if ($product->track_expiry) {
                if (! $batchId) {
                    throw new DomainException('Expiry-tracked write-offs must target a specific batch.');
                }

                $batch = ProductBatch::query()
                    ->whereKey($batchId)
                    ->where('product_id', $product->id)
                    ->first();

                if (! $batch) {
                    throw new DomainException('The selected inventory write-off batch is unavailable.');
                }
            } elseif ($batchId) {
                throw new DomainException('A batch cannot be selected for a product that does not track expiry.');
            }

            if ($type === 'expiry' && ! $product->track_expiry) {
                throw new DomainException('Expiry write-off is only valid for expiry-tracked products.');
            }

            $quantity = Decimal::normalize($input['quantity_base'] ?? '0');

            if (! Decimal::isPositive($quantity)) {
                throw new DomainException('Inventory write-off quantity must be greater than zero.');
            }

            $prepared->push([
                'product' => $product,
                'batch' => $batch,
                'quantity' => $quantity,
            ]);
        }

        return $prepared;
    }

    private function parseTarget(string $target): array
    {
        if (! preg_match('/^(\d+):(\d*)$/', $target, $matches)) {
            throw new DomainException('Invalid inventory write-off target.');
        }

        return [(int) $matches[1], $matches[2] !== '' ? (int) $matches[2] : null];
    }

    private function assertRetryMatches(InventoryWriteoff $existing, array $data): void
    {
        $requestedType = (string) ($data['writeoff_type'] ?? '');

        if (
            $existing->writeoff_type !== $requestedType
            || trim((string) $existing->reason) !== trim((string) ($data['reason'] ?? ''))
            || trim((string) ($existing->notes ?? '')) !== trim((string) ($data['notes'] ?? ''))
        ) {
            throw new DomainException('The inventory write-off idempotency key is already bound to another payload.');
        }

        $prepared = $this->prepareTargets($data['items'] ?? [], $requestedType);
        $requested = $prepared
            ->map(fn (array $line) => [
                'product_id' => (int) $line['product']->id,
                'batch_id' => $line['batch']?->id,
                'quantity' => $line['quantity'],
            ])
            ->sortBy(fn (array $line) => $line['product_id'].':'.($line['batch_id'] ?? ''))
            ->values()
            ->all();

        $recorded = $existing->items
            ->map(fn ($item) => [
                'product_id' => (int) $item->product_id,
                'batch_id' => $item->product_batch_id ? (int) $item->product_batch_id : null,
                'quantity' => Decimal::normalize($item->quantity_base),
            ])
            ->sortBy(fn (array $line) => $line['product_id'].':'.($line['batch_id'] ?? ''))
            ->values()
            ->all();

        if ($requested !== $recorded) {
            throw new DomainException('The inventory write-off idempotency key is already bound to another item set.');
        }
    }
}
