<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Closing\BusinessDayService;
use App\Services\Documents\DocumentNumberService;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockCountService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly InventoryService $inventory,
        private readonly InventoryCostService $costing,
        private readonly BusinessDayService $days,
        private readonly AuditLogger $audit,
    ) {
    }

    public function createDraft(array $data, User $actor): StockCount
    {
        if (! $actor->hasPermission('inventory.count')) {
            throw new DomainException('The user is not allowed to perform stock counts.');
        }

        return DB::transaction(function () use ($data, $actor): StockCount {
            if ($existing = StockCount::query()
                ->with(['items.product', 'items.batch'])
                ->where('idempotency_key', $data['idempotency_key'])
                ->first()) {
                $this->assertCreateRetryMatches($existing, $data);

                return $existing;
            }

            $prepared = $this->prepareTargets($data['items'] ?? []);

            if ($prepared->isEmpty()) {
                throw new DomainException('A stock count requires at least one item.');
            }

            $count = StockCount::create([
                'number' => $this->numbers->next('stock_count', 'SCN'),
                'idempotency_key' => $data['idempotency_key'],
                'counted_by_user_id' => $actor->id,
                'status' => 'draft',
                'counted_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($prepared as $line) {
                $count->items()->create([
                    'product_id' => $line['product']->id,
                    'product_batch_id' => $line['batch']?->id,
                    'expected_quantity_base' => $line['expected'],
                    'physical_quantity_base' => $line['physical'],
                    'variance_quantity_base' => Decimal::subtract(
                        $line['physical'],
                        $line['expected'],
                    ),
                    'cost_amount' => '0.0000',
                ]);
            }

            $this->audit->record(
                'inventory.stock_count.created',
                model: $count,
                newValues: [
                    'number' => $count->number,
                    'line_count' => $prepared->count(),
                ],
                actor: $actor,
            );

            return $count->fresh(['items.product', 'items.batch', 'countedBy']);
        });
    }

    public function approve(StockCount $count, array $data, User $actor): StockCount
    {
        if (! $actor->hasPermission('inventory.count.approve')) {
            throw new DomainException('The user is not allowed to approve stock counts.');
        }

        return DB::transaction(function () use ($count, $data, $actor): StockCount {
            $candidate = StockCount::query()->findOrFail($count->id);

            if ($candidate->status === 'approved') {
                if (
                    $candidate->approval_idempotency_key === ($data['idempotency_key'] ?? null)
                ) {
                    return $candidate->load(['items.product', 'items.batch', 'items.stockMovement', 'approvedBy']);
                }

                throw new DomainException('This stock count has already been approved.');
            }

            $this->days->lockOpen(now());

            $locked = StockCount::query()
                ->with(['items.product', 'items.batch'])
                ->lockForUpdate()
                ->findOrFail($count->id);

            if ($locked->status !== 'draft') {
                throw new DomainException('Only draft stock counts can be approved.');
            }

            foreach ($locked->items as $item) {
                $product = Product::query()->lockForUpdate()->findOrFail($item->product_id);
                $batch = $item->product_batch_id
                    ? ProductBatch::query()->lockForUpdate()->findOrFail($item->product_batch_id)
                    : null;

                if ($product->track_expiry && ! $batch) {
                    throw new DomainException('Expiry-tracked stock counts must target a specific batch.');
                }

                $current = $batch
                    ? Decimal::normalize($batch->stock_on_hand)
                    : Decimal::normalize($product->stock_on_hand);

                if (Decimal::compare($current, $item->expected_quantity_base) !== 0) {
                    throw new DomainException(
                        'Stock changed after count '.$locked->number.' was captured. Recount the affected item before approval.'
                    );
                }

                $variance = Decimal::subtract(
                    $item->physical_quantity_base,
                    $item->expected_quantity_base,
                );

                if (Decimal::compare($variance, '0') === 0) {
                    continue;
                }

                if (Decimal::isPositive($variance)) {
                    $movement = $this->inventory->recordMovement(
                        product: $product,
                        type: StockMovementType::AdjustmentIn,
                        baseQuantity: $variance,
                        batch: $batch,
                        unitCostBase: $product->purchase_cost,
                        actor: $actor,
                        notes: 'Stock count '.$locked->number.' positive variance',
                        referenceType: StockCountItem::class,
                        referenceId: $item->id,
                        idempotencyKey: 'stock-count:'.$locked->id.':item:'.$item->id,
                    );

                    $this->costing->registerInboundMovement($movement);

                    $costAmount = Decimal::multiplyRounded(
                        $variance,
                        Decimal::normalize($product->purchase_cost, 4),
                        4,
                    );
                } else {
                    $lossQuantity = Decimal::subtract('0', $variance);
                    $movement = $this->inventory->recordMovement(
                        product: $product,
                        type: StockMovementType::AdjustmentOut,
                        baseQuantity: '-'.$lossQuantity,
                        batch: $batch,
                        actor: $actor,
                        notes: 'Stock count '.$locked->number.' negative variance',
                        referenceType: StockCountItem::class,
                        referenceId: $item->id,
                        idempotencyKey: 'stock-count:'.$locked->id.':item:'.$item->id,
                    );

                    $costAmount = $this->costing->consumeForAdjustment(
                        movement: $movement,
                        product: $product,
                        quantityBase: $lossQuantity,
                        batch: $batch,
                    );
                }

                $item->forceFill([
                    'stock_movement_id' => $movement->id,
                    'cost_amount' => $costAmount,
                ])->save();
            }

            $locked->forceFill([
                'approval_idempotency_key' => $data['idempotency_key'],
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'status' => 'approved',
            ])->save();

            $this->audit->record(
                'inventory.stock_count.approved',
                model: $locked,
                newValues: [
                    'number' => $locked->number,
                    'approved_by_user_id' => $actor->id,
                ],
                actor: $actor,
            );

            return $locked->fresh([
                'items.product',
                'items.batch',
                'items.stockMovement',
                'countedBy',
                'approvedBy',
            ]);
        });
    }

    private function prepareTargets(array $items): Collection
    {
        $prepared = collect();
        $seen = [];

        foreach ($items as $input) {
            [$productId, $batchId] = $this->parseTarget((string) ($input['target'] ?? ''));
            $key = $productId.':'.($batchId ?? '');

            if (isset($seen[$key])) {
                throw new DomainException('A stock count cannot contain the same product/batch twice.');
            }

            $seen[$key] = true;
            $product = Product::query()->whereKey($productId)->where('track_stock', true)->first();

            if (! $product) {
                throw new DomainException('The selected stock-count product is unavailable.');
            }

            $batch = null;

            if ($product->track_expiry) {
                if (! $batchId) {
                    throw new DomainException('Expiry-tracked stock counts must target a specific batch.');
                }

                $batch = ProductBatch::query()
                    ->whereKey($batchId)
                    ->where('product_id', $product->id)
                    ->first();

                if (! $batch) {
                    throw new DomainException('The selected stock-count batch is unavailable.');
                }
            } elseif ($batchId) {
                throw new DomainException('A batch cannot be selected for a product that does not track expiry.');
            }

            $physical = Decimal::normalize($input['physical_quantity_base'] ?? '0');

            if (Decimal::isNegative($physical)) {
                throw new DomainException('Physical stock count cannot be negative.');
            }

            $prepared->push([
                'product' => $product,
                'batch' => $batch,
                'expected' => $batch
                    ? Decimal::normalize($batch->stock_on_hand)
                    : Decimal::normalize($product->stock_on_hand),
                'physical' => $physical,
            ]);
        }

        return $prepared;
    }

    private function parseTarget(string $target): array
    {
        if (! preg_match('/^(\d+):(\d*)$/', $target, $matches)) {
            throw new DomainException('Invalid stock-count target.');
        }

        return [(int) $matches[1], $matches[2] !== '' ? (int) $matches[2] : null];
    }

    private function assertCreateRetryMatches(StockCount $existing, array $data): void
    {
        $prepared = $this->prepareTargets($data['items'] ?? []);
        $requested = $prepared
            ->map(fn (array $line) => [
                'product_id' => (int) $line['product']->id,
                'batch_id' => $line['batch']?->id,
                'physical' => $line['physical'],
            ])
            ->sortBy(fn (array $line) => $line['product_id'].':'.($line['batch_id'] ?? ''))
            ->values()
            ->all();

        $recorded = $existing->items
            ->map(fn (StockCountItem $item) => [
                'product_id' => (int) $item->product_id,
                'batch_id' => $item->product_batch_id ? (int) $item->product_batch_id : null,
                'physical' => Decimal::normalize($item->physical_quantity_base),
            ])
            ->sortBy(fn (array $line) => $line['product_id'].':'.($line['batch_id'] ?? ''))
            ->values()
            ->all();

        if (
            $requested !== $recorded
            || trim((string) ($existing->notes ?? '')) !== trim((string) ($data['notes'] ?? ''))
        ) {
            throw new DomainException('The stock-count idempotency key is already bound to another payload.');
        }
    }
}
