<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\GoodsReceiptItem;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\SaleReturnStockAllocation;
use App\Models\SaleReturnItem;
use App\Models\SaleItem;
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
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly InventoryCostService $costLayers,
    ) {
    }

    public function addOpeningStock(
        Product $product,
        string $sourceQuantity,
        int $sourceUnitId,
        ?array $batchData = null,
        ?string $sourceUnitCost = null,
        ?User $actor = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $product,
            $sourceQuantity,
            $sourceUnitId,
            $batchData,
            $sourceUnitCost,
            $actor,
            $notes,
            $idempotencyKey,
        ): StockMovement {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->getKey());

            if ($existing = $this->existingIdempotentMovement($lockedProduct, $idempotencyKey)) {
                $this->costLayers->registerInboundMovement($existing);

                return $existing;
            }

            if (! $lockedProduct->track_stock) {
                throw new DomainException('Opening stock cannot be recorded for a product that does not track stock.');
            }

            $sourceUnit = ProductUnit::query()
                ->with('unit')
                ->where('product_id', $lockedProduct->id)
                ->where('unit_id', $sourceUnitId)
                ->firstOrFail();

            $normalizedSourceQuantity = $this->normalizeSourceQuantity($sourceUnit, $sourceQuantity);
            $baseQuantity = Decimal::multiply($normalizedSourceQuantity, $sourceUnit->conversion_factor);
            $normalizedSourceCost = $sourceUnitCost !== null ? Decimal::normalize($sourceUnitCost, 4) : null;
            $baseUnitCost = $normalizedSourceCost !== null
                ? Decimal::divide($normalizedSourceCost, $sourceUnit->conversion_factor, 4)
                : null;

            $batch = $this->resolveBatch($lockedProduct, $batchData);

            $movement = $this->applyLockedMovement(
                product: $lockedProduct,
                type: StockMovementType::OpeningStock,
                baseQuantity: $baseQuantity,
                batch: $batch,
                sourceUnit: $sourceUnit,
                sourceQuantity: $normalizedSourceQuantity,
                sourceUnitCost: $normalizedSourceCost,
                unitCostBase: $baseUnitCost,
                actor: $actor,
                notes: $notes,
                idempotencyKey: $idempotencyKey ?: (string) Str::uuid(),
            );

            $this->costLayers->registerInboundMovement($movement);

            return $movement;
        });
    }

    public function receivePurchaseStock(
        ProductUnit $productUnit,
        string $sourceQuantity,
        ?array $batchData,
        string $sourceUnitLandedCost,
        string $baseUnitLandedCost,
        ?User $actor,
        string $referenceType,
        int $referenceId,
        string $idempotencyKey,
        ?string $notes = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $productUnit,
            $sourceQuantity,
            $batchData,
            $sourceUnitLandedCost,
            $baseUnitLandedCost,
            $actor,
            $referenceType,
            $referenceId,
            $idempotencyKey,
            $notes,
        ): StockMovement {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($productUnit->product_id);

            if ($existing = $this->existingIdempotentMovement($lockedProduct, $idempotencyKey)) {
                $this->costLayers->registerInboundMovement($existing);

                return $existing;
            }

            $sourceUnit = ProductUnit::query()
                ->with('unit')
                ->whereKey($productUnit->id)
                ->where('product_id', $lockedProduct->id)
                ->where('can_purchase', true)
                ->firstOrFail();

            $normalizedSourceQuantity = $this->normalizeSourceQuantity($sourceUnit, $sourceQuantity);
            $baseQuantity = Decimal::multiply($normalizedSourceQuantity, $sourceUnit->conversion_factor);
            $normalizedSourceCost = Decimal::normalize($sourceUnitLandedCost, 4);
            $normalizedBaseCost = Decimal::normalize($baseUnitLandedCost, 4);
            $batch = $this->resolveBatch($lockedProduct, $batchData);

            $movement = $this->applyLockedMovement(
                product: $lockedProduct,
                type: StockMovementType::Purchase,
                baseQuantity: $baseQuantity,
                batch: $batch,
                sourceUnit: $sourceUnit,
                sourceQuantity: $normalizedSourceQuantity,
                sourceUnitCost: $normalizedSourceCost,
                unitCostBase: $normalizedBaseCost,
                actor: $actor,
                notes: $notes,
                referenceType: $referenceType,
                referenceId: $referenceId,
                idempotencyKey: $idempotencyKey,
            );

            $this->costLayers->registerInboundMovement($movement);

            $lockedProduct->forceFill([
                'purchase_cost' => Decimal::round($normalizedBaseCost, 2),
            ])->save();

            return $movement;
        });
    }

    public function deductSaleStock(
        ProductUnit $productUnit,
        string $sourceQuantity,
        ?User $actor,
        string $referenceType,
        int $referenceId,
        ?string $notes = null,
    ): array {
        return DB::transaction(function () use (
            $productUnit,
            $sourceQuantity,
            $actor,
            $referenceType,
            $referenceId,
            $notes,
        ): array {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($productUnit->product_id);

            if (! $lockedProduct->track_stock) {
                return [];
            }

            $sourceUnit = ProductUnit::query()
                ->with('unit')
                ->whereKey($productUnit->id)
                ->where('product_id', $lockedProduct->id)
                ->where('can_sell', true)
                ->firstOrFail();

            $normalizedSourceQuantity = $this->normalizeSourceQuantity($sourceUnit, $sourceQuantity);
            $baseQuantity = Decimal::multiply($normalizedSourceQuantity, $sourceUnit->conversion_factor);

            if (! $lockedProduct->track_expiry) {
                return [[
                    'movement' => $this->applyLockedMovement(
                        product: $lockedProduct,
                        type: StockMovementType::Sale,
                        baseQuantity: '-'.$baseQuantity,
                        sourceUnit: $sourceUnit,
                        sourceQuantity: '-'.$normalizedSourceQuantity,
                        actor: $actor,
                        notes: $notes,
                        referenceType: $referenceType,
                        referenceId: $referenceId,
                        idempotencyKey: (string) Str::uuid(),
                    ),
                    'batch_id' => null,
                    'quantity_base' => $baseQuantity,
                ]];
            }

            $remaining = $baseQuantity;
            $allocations = [];

            $batches = ProductBatch::query()
                ->where('product_id', $lockedProduct->id)
                ->where('is_blocked', false)
                ->whereDate('expires_at', '>=', today())
                ->where('stock_on_hand', '>', 0)
                ->orderBy('expires_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($batches as $batch) {
                if (Decimal::compare($remaining, '0') <= 0) {
                    break;
                }

                $take = Decimal::compare($batch->stock_on_hand, $remaining) <= 0
                    ? $batch->stock_on_hand
                    : $remaining;

                if (! Decimal::isPositive($take)) {
                    continue;
                }

                $movement = $this->applyLockedMovement(
                    product: $lockedProduct,
                    type: StockMovementType::Sale,
                    baseQuantity: '-'.$take,
                    batch: $batch,
                    sourceUnit: $sourceUnit,
                    actor: $actor,
                    notes: $notes,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                    idempotencyKey: (string) Str::uuid(),
                );

                $allocations[] = [
                    'movement' => $movement,
                    'batch_id' => $batch->id,
                    'quantity_base' => Decimal::normalize($take),
                ];

                $remaining = Decimal::subtract($remaining, $take);
            }

            if (Decimal::isPositive($remaining)) {
                throw new DomainException('Insufficient non-expired batch stock for this product.');
            }

            return $allocations;
        });
    }

    public function returnPurchaseStock(
        GoodsReceiptItem $receiptItem,
        PurchaseReturn $purchaseReturn,
        string $quantityBase,
        ?User $actor,
        ?string $notes = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $receiptItem,
            $purchaseReturn,
            $quantityBase,
            $actor,
            $notes,
        ): StockMovement {
            $receiptItem->loadMissing(['product', 'stockMovement.batch']);

            if (! $receiptItem->stockMovement) {
                throw new DomainException('Goods receipt item has no posted stock movement.');
            }

            $quantityBase = Decimal::normalize($quantityBase);

            if (! Decimal::isPositive($quantityBase)) {
                throw new DomainException('Purchase return stock quantity must be greater than zero.');
            }

            if (Decimal::compare($quantityBase, $receiptItem->product->stock_on_hand) > 0) {
                throw new DomainException('Purchase return quantity exceeds the available physical product stock.');
            }

            if (
                $receiptItem->stockMovement->batch
                && Decimal::compare($quantityBase, $receiptItem->stockMovement->batch->stock_on_hand) > 0
            ) {
                throw new DomainException('Purchase return quantity exceeds the available original batch stock.');
            }

            return $this->recordMovement(
                product: $receiptItem->product,
                type: StockMovementType::PurchaseReturn,
                baseQuantity: '-'.$quantityBase,
                batch: $receiptItem->stockMovement->batch,
                unitCostBase: $receiptItem->base_unit_landed_cost,
                actor: $actor,
                notes: $notes,
                referenceType: PurchaseReturn::class,
                referenceId: $purchaseReturn->id,
                idempotencyKey: 'purchase-return:'.$purchaseReturn->id.':receipt-item:'.$receiptItem->id,
            );
        });
    }

    public function restoreSaleReturnStock(
        SaleReturnItem $returnItem,
        SaleItem $saleItem,
        string $quantityBase,
        ?User $actor,
        ?string $notes = null,
    ): array {
        return DB::transaction(function () use ($returnItem, $saleItem, $quantityBase, $actor, $notes): array {
            $remaining = Decimal::normalize($quantityBase);

            if (! Decimal::isPositive($remaining)) {
                throw new DomainException('Return stock quantity must be greater than zero.');
            }

            $allocations = $saleItem->stockAllocations()
                ->with('batch')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($allocations->isEmpty()) {
                return [];
            }

            $restored = [];

            foreach ($allocations as $allocation) {
                if (! Decimal::isPositive($remaining)) {
                    break;
                }

                $alreadyRestored = SaleReturnStockAllocation::query()
                    ->where('original_sale_stock_allocation_id', $allocation->id)
                    ->sum('quantity_base');
                $alreadyRestored = Decimal::normalize((string) $alreadyRestored);

                $available = Decimal::subtract($allocation->quantity_base, $alreadyRestored);

                if (! Decimal::isPositive($available)) {
                    continue;
                }

                $take = Decimal::compare($available, $remaining) <= 0 ? $available : $remaining;
                $movement = $this->recordMovement(
                    product: $saleItem->product,
                    type: StockMovementType::SaleReturn,
                    baseQuantity: $take,
                    batch: $allocation->batch,
                    actor: $actor,
                    notes: $notes,
                    referenceType: SaleReturnItem::class,
                    referenceId: $returnItem->id,
                    idempotencyKey: 'sale-return:'.$returnItem->id.':stock:'.$allocation->id,
                );

                $record = SaleReturnStockAllocation::create([
                    'sale_return_item_id' => $returnItem->id,
                    'original_sale_stock_allocation_id' => $allocation->id,
                    'product_batch_id' => $allocation->product_batch_id,
                    'stock_movement_id' => $movement->id,
                    'quantity_base' => $take,
                ]);

                $restored[] = $record;
                $remaining = Decimal::subtract($remaining, $take);
            }

            if (Decimal::isPositive($remaining)) {
                throw new DomainException('Return stock restoration exceeds the sale item stock history.');
            }

            return $restored;
        });
    }

    public function recordMovement(
        Product $product,
        StockMovementType $type,
        string $baseQuantity,
        ?ProductBatch $batch = null,
        ?string $unitCostBase = null,
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
            $unitCostBase,
            $actor,
            $notes,
            $referenceType,
            $referenceId,
            $idempotencyKey,
        ): StockMovement {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->getKey());

            if ($existing = $this->existingIdempotentMovement($lockedProduct, $idempotencyKey)) {
                return $existing;
            }

            $lockedBatch = $batch
                ? ProductBatch::query()->lockForUpdate()->findOrFail($batch->getKey())
                : null;

            return $this->applyLockedMovement(
                product: $lockedProduct,
                type: $type,
                baseQuantity: Decimal::normalize($baseQuantity),
                batch: $lockedBatch,
                unitCostBase: $unitCostBase !== null ? Decimal::normalize($unitCostBase, 4) : null,
                actor: $actor,
                notes: $notes,
                referenceType: $referenceType,
                referenceId: $referenceId,
                idempotencyKey: $idempotencyKey ?: (string) Str::uuid(),
            );
        });
    }

    private function normalizeSourceQuantity(ProductUnit $productUnit, string $quantity): string
    {
        $normalized = Decimal::normalize($quantity);

        if (! Decimal::isPositive($normalized)) {
            throw new DomainException('Stock quantity must be greater than zero.');
        }

        if (Decimal::fractionalDigits($quantity) > $productUnit->unit->decimal_places) {
            throw new DomainException('Quantity exceeds the decimal precision allowed by the selected unit.');
        }

        return $normalized;
    }

    private function existingIdempotentMovement(Product $product, ?string $idempotencyKey): ?StockMovement
    {
        if (! $idempotencyKey) {
            return null;
        }

        $existing = StockMovement::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing && (int) $existing->product_id !== (int) $product->id) {
            throw new DomainException('The idempotency key belongs to another product movement.');
        }

        return $existing;
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

        $batch = ProductBatch::query()
            ->where('product_id', $product->id)
            ->where('batch_number', $batchData['batch_number'])
            ->lockForUpdate()
            ->first();

        if ($batch) {
            if ($batch->is_blocked) {
                throw new DomainException('Stock cannot be added to a blocked batch.');
            }

            if (! empty($batchData['expires_at']) && $batch->expires_at?->format('Y-m-d') !== $batchData['expires_at']) {
                throw new DomainException('The expiry date does not match the existing batch.');
            }

            return $batch;
        }

        return ProductBatch::create([
            'product_id' => $product->id,
            'batch_number' => $batchData['batch_number'],
            'manufactured_at' => $batchData['manufactured_at'] ?? null,
            'expires_at' => $batchData['expires_at'] ?? null,
            'notes' => $batchData['notes'] ?? null,
            'stock_on_hand' => '0',
        ]);
    }

    private function applyLockedMovement(
        Product $product,
        StockMovementType $type,
        string $baseQuantity,
        ?ProductBatch $batch = null,
        ?ProductUnit $sourceUnit = null,
        ?string $sourceQuantity = null,
        ?string $sourceUnitCost = null,
        ?string $unitCostBase = null,
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
            'source_unit_cost' => $sourceUnitCost,
            'unit_cost_base' => $unitCostBase,
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
                'unit_cost_base' => $unitCostBase,
            ],
            actor: $actor,
        );

        return $movement;
    }
}
