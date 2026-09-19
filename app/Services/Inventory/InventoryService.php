<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\ShopSetting;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function addOpeningStock(
        Product $product,
        string $sourceQuantity,
        int $sourceUnitId,
        ?array $batchData = null,
        ?string $unitCost = null,
        ?User $actor = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $product,
            $sourceQuantity,
            $sourceUnitId,
            $batchData,
            $unitCost,
            $actor,
            $notes,
            $idempotencyKey,
        ): StockMovement {
            if ($idempotencyKey) {
                $existing = StockMovement::query()->where('idempotency_key', $idempotencyKey)->first();

                if ($existing) {
                    return $existing;
                }
            }

            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->getKey());

            if (! $lockedProduct->track_stock) {
                throw new DomainException('Opening stock cannot be recorded for a product that does not track stock.');
            }

            $sourceUnit = ProductUnit::query()
                ->where('product_id', $lockedProduct->id)
                ->where('unit_id', $sourceUnitId)
                ->firstOrFail();

            $normalizedSourceQuantity = Decimal::normalize($sourceQuantity);

            if (! Decimal::isPositive($normalizedSourceQuantity)) {
                throw new DomainException('Opening stock quantity must be greater than zero.');
            }

            $baseQuantity = Decimal::multiply(
                $normalizedSourceQuantity,
                $sourceUnit->conversion_factor,
            );

            $batch = $this->resolveBatch($lockedProduct, $batchData);

            return $this->applyLockedMovement(
                product: $lockedProduct,
                type: StockMovementType::OpeningStock,
                baseQuantity: $baseQuantity,
                batch: $batch,
                sourceUnit: $sourceUnit,
                sourceQuantity: $normalizedSourceQuantity,
                unitCost: $unitCost,
                actor: $actor,
                notes: $notes,
                idempotencyKey: $idempotencyKey ?: (string) Str::uuid(),
            );
        });
    }

    public function recordMovement(
        Product $product,
        StockMovementType $type,
        string $baseQuantity,
        ?ProductBatch $batch = null,
        ?string $unitCost = null,
        ?User $actor = null,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $idempotencyKey = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $product,
            $type,
            $baseQuantity,
            $batch,
            $unitCost,
            $actor,
            $notes,
            $referenceType,
            $referenceId,
            $idempotencyKey,
        ): StockMovement {
            if ($idempotencyKey) {
                $existing = StockMovement::query()->where('idempotency_key', $idempotencyKey)->first();

                if ($existing) {
                    return $existing;
                }
            }

            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $lockedBatch = $batch
                ? ProductBatch::query()->lockForUpdate()->findOrFail($batch->getKey())
                : null;

            return $this->applyLockedMovement(
                product: $lockedProduct,
                type: $type,
                baseQuantity: Decimal::normalize($baseQuantity),
                batch: $lockedBatch,
                unitCost: $unitCost,
                actor: $actor,
                notes: $notes,
                referenceType: $referenceType,
                referenceId: $referenceId,
                idempotencyKey: $idempotencyKey ?: (string) Str::uuid(),
            );
        });
    }

    private function resolveBatch(Product $product, ?array $batchData): ?ProductBatch
    {
        if ($product->track_expiry && empty($batchData['batch_number'])) {
            throw new DomainException('A batch number is required for expiry-tracked products.');
        }

        if ($product->track_expiry && empty($batchData['expires_at'])) {
            throw new DomainException('An expiry date is required for expiry-tracked products.');
        }

        if (empty($batchData['batch_number'])) {
            return null;
        }

        $batch = ProductBatch::query()->firstOrCreate(
            [
                'product_id' => $product->id,
                'batch_number' => $batchData['batch_number'],
            ],
            [
                'manufactured_at' => $batchData['manufactured_at'] ?? null,
                'expires_at' => $batchData['expires_at'] ?? null,
                'notes' => $batchData['notes'] ?? null,
                'stock_on_hand' => '0',
            ],
        );

        return ProductBatch::query()->lockForUpdate()->findOrFail($batch->id);
    }

    private function applyLockedMovement(
        Product $product,
        StockMovementType $type,
        string $baseQuantity,
        ?ProductBatch $batch = null,
        ?ProductUnit $sourceUnit = null,
        ?string $sourceQuantity = null,
        ?string $unitCost = null,
        ?User $actor = null,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        string $idempotencyKey = '',
    ): StockMovement {
        if (! $product->track_stock) {
            throw new DomainException('Stock movement is not allowed for a product that does not track stock.');
        }

        if ($product->track_expiry && ! $batch) {
            throw new DomainException('A batch is required for expiry-tracked product movements.');
        }

        if ($batch && (int) $batch->product_id !== (int) $product->id) {
            throw new DomainException('The selected batch does not belong to this product.');
        }

        if (Decimal::compare($baseQuantity, '0') === 0) {
            throw new DomainException('Stock movement quantity cannot be zero.');
        }

        $newProductBalance = Decimal::add($product->stock_on_hand, $baseQuantity);
        $negativeStockAllowed = (bool) (ShopSetting::query()->value('negative_stock_enabled') ?? false);

        if (Decimal::isNegative($newProductBalance) && ! $negativeStockAllowed) {
            throw new DomainException('Insufficient stock. Negative stock is disabled.');
        }

        $newBatchBalance = null;

        if ($batch) {
            $newBatchBalance = Decimal::add($batch->stock_on_hand, $baseQuantity);

            if (Decimal::isNegative($newBatchBalance)) {
                throw new DomainException('A batch balance cannot become negative.');
            }

            $batch->forceFill(['stock_on_hand' => $newBatchBalance])->save();
        }

        $product->forceFill(['stock_on_hand' => $newProductBalance])->save();

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'product_batch_id' => $batch?->id,
            'source_unit_id' => $sourceUnit?->unit_id,
            'actor_user_id' => $actor?->id,
            'movement_type' => $type,
            'source_quantity' => $sourceQuantity,
            'conversion_factor' => $sourceUnit?->conversion_factor,
            'quantity_base' => $baseQuantity,
            'balance_after' => $newProductBalance,
            'batch_balance_after' => $newBatchBalance,
            'unit_cost' => $unitCost !== null ? Decimal::normalize($unitCost, 4) : null,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'idempotency_key' => $idempotencyKey ?: (string) Str::uuid(),
            'notes' => $notes,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        $this->audit->record(
            'inventory.stock.moved',
            model: $product,
            newValues: [
                'movement_id' => $movement->id,
                'movement_type' => $type->value,
                'quantity_base' => $baseQuantity,
                'balance_after' => $newProductBalance,
                'batch_id' => $batch?->id,
            ],
            actor: $actor,
        );

        return $movement;
    }
}
