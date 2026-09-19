<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryCostLayer;
use App\Models\InventoryCostLayerConsumption;
use App\Models\InventoryCostLayerRestoration;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\SaleReturnItem;
use App\Models\StockMovement;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class InventoryCostService
{
    public function registerInboundMovement(StockMovement $movement): ?InventoryCostLayer
    {
        if (
            ! in_array($movement->movement_type, [StockMovementType::OpeningStock, StockMovementType::Purchase], true)
            || ! Decimal::isPositive($movement->quantity_base)
        ) {
            return null;
        }

        return InventoryCostLayer::query()->firstOrCreate(
            ['source_stock_movement_id' => $movement->id],
            [
                'product_id' => $movement->product_id,
                'product_batch_id' => $movement->product_batch_id,
                'initial_quantity_base' => Decimal::normalize($movement->quantity_base),
                'remaining_quantity_base' => Decimal::normalize($movement->quantity_base),
                'unit_cost_base' => Decimal::normalize($movement->unit_cost_base ?? '0', 4),
                'received_at' => $movement->occurred_at,
            ],
        );
    }

    public function consumeForSaleItem(
        SaleItem $saleItem,
        Product $product,
        string $quantityBase,
    ): string {
        return DB::transaction(function () use ($saleItem, $product, $quantityBase): string {
            $remaining = Decimal::normalize($quantityBase);
            $totalCost = '0.0000';

            $layers = InventoryCostLayer::query()
                ->where('product_id', $product->id)
                ->where('remaining_quantity_base', '>', 0)
                ->orderBy('received_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($layers as $layer) {
                if (! Decimal::isPositive($remaining)) {
                    break;
                }

                $take = Decimal::compare($layer->remaining_quantity_base, $remaining) <= 0
                    ? $layer->remaining_quantity_base
                    : $remaining;

                if (! Decimal::isPositive($take)) {
                    continue;
                }

                $cost = Decimal::multiplyRounded($take, $layer->unit_cost_base, 4);

                InventoryCostLayerConsumption::create([
                    'inventory_cost_layer_id' => $layer->id,
                    'sale_item_id' => $saleItem->id,
                    'quantity_base' => $take,
                    'unit_cost_base' => $layer->unit_cost_base,
                    'cost_amount' => $cost,
                    'cost_source' => 'fifo',
                ]);

                $layer->forceFill([
                    'remaining_quantity_base' => Decimal::subtract($layer->remaining_quantity_base, $take),
                ])->save();

                $totalCost = Decimal::add($totalCost, $cost, 4);
                $remaining = Decimal::subtract($remaining, $take);
            }

            if (Decimal::isPositive($remaining)) {
                $fallbackUnitCost = Decimal::normalize($product->purchase_cost, 4);
                $fallbackCost = Decimal::multiplyRounded($remaining, $fallbackUnitCost, 4);

                InventoryCostLayerConsumption::create([
                    'inventory_cost_layer_id' => null,
                    'sale_item_id' => $saleItem->id,
                    'quantity_base' => $remaining,
                    'unit_cost_base' => $fallbackUnitCost,
                    'cost_amount' => $fallbackCost,
                    'cost_source' => $product->track_stock ? 'fallback_latest' : 'untracked',
                ]);

                $totalCost = Decimal::add($totalCost, $fallbackCost, 4);
            }

            return Decimal::round($totalCost, 2);
        });
    }

    public function removeForPurchaseReturn(
        GoodsReceiptItem $receiptItem,
        string $quantityBase,
    ): array {
        return DB::transaction(function () use ($receiptItem, $quantityBase): array {
            $quantityBase = Decimal::normalize($quantityBase);

            if (! Decimal::isPositive($quantityBase)) {
                throw new DomainException('Purchase return cost quantity must be greater than zero.');
            }

            if (! $receiptItem->stock_movement_id) {
                throw new DomainException('Goods receipt item has no posted stock movement.');
            }

            $layer = InventoryCostLayer::query()
                ->where('source_stock_movement_id', $receiptItem->stock_movement_id)
                ->lockForUpdate()
                ->first();

            if (! $layer) {
                throw new DomainException('The original purchase cost layer is unavailable.');
            }

            if (Decimal::compare($quantityBase, $layer->remaining_quantity_base) > 0) {
                throw new DomainException('Purchase return quantity exceeds inventory still available from this receipt.');
            }

            $costAmount = Decimal::multiplyRounded(
                $quantityBase,
                $layer->unit_cost_base,
                4,
            );

            $layer->forceFill([
                'remaining_quantity_base' => Decimal::subtract(
                    $layer->remaining_quantity_base,
                    $quantityBase,
                ),
            ])->save();

            return [
                'layer' => $layer,
                'unit_cost_base' => Decimal::normalize($layer->unit_cost_base, 4),
                'cost_amount' => $costAmount,
            ];
        });
    }

    public function restoreForReturnItem(
        SaleReturnItem $returnItem,
        SaleItem $saleItem,
        string $quantityBase,
    ): string {
        return DB::transaction(function () use ($returnItem, $saleItem, $quantityBase): string {
            $remaining = Decimal::normalize($quantityBase);
            $totalCost = '0.0000';

            $consumptions = InventoryCostLayerConsumption::query()
                ->where('sale_item_id', $saleItem->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($consumptions as $consumption) {
                if (! Decimal::isPositive($remaining)) {
                    break;
                }

                $alreadyRestoredQty = InventoryCostLayerRestoration::query()
                    ->where('original_consumption_id', $consumption->id)
                    ->sum('quantity_base');
                $alreadyRestoredQty = Decimal::normalize((string) $alreadyRestoredQty);

                $available = Decimal::subtract($consumption->quantity_base, $alreadyRestoredQty);

                if (! Decimal::isPositive($available)) {
                    continue;
                }

                $take = Decimal::compare($available, $remaining) <= 0 ? $available : $remaining;

                $alreadyRestoredCost = InventoryCostLayerRestoration::query()
                    ->where('original_consumption_id', $consumption->id)
                    ->sum('cost_amount');
                $alreadyRestoredCost = Decimal::normalize((string) $alreadyRestoredCost, 4);
                $remainingOriginalCost = Decimal::subtract(
                    $consumption->cost_amount,
                    $alreadyRestoredCost,
                    4,
                );

                $cost = Decimal::compare($take, $available) === 0
                    ? $remainingOriginalCost
                    : Decimal::multiplyRounded($take, $consumption->unit_cost_base, 4);

                $layerId = $consumption->inventory_cost_layer_id;

                if ($layerId) {
                    $layer = InventoryCostLayer::query()->lockForUpdate()->findOrFail($layerId);
                    $newRemaining = Decimal::add($layer->remaining_quantity_base, $take);

                    if (Decimal::compare($newRemaining, $layer->initial_quantity_base) > 0) {
                        throw new DomainException('Cost-layer restoration would exceed its original quantity.');
                    }

                    $layer->forceFill(['remaining_quantity_base' => $newRemaining])->save();
                } elseif ($saleItem->product->track_stock) {
                    $priorSyntheticLayerId = InventoryCostLayerRestoration::query()
                        ->where('original_consumption_id', $consumption->id)
                        ->whereNotNull('inventory_cost_layer_id')
                        ->value('inventory_cost_layer_id');

                    if ($priorSyntheticLayerId) {
                        $layer = InventoryCostLayer::query()->lockForUpdate()->findOrFail($priorSyntheticLayerId);
                        $layer->forceFill([
                            'initial_quantity_base' => Decimal::add($layer->initial_quantity_base, $take),
                            'remaining_quantity_base' => Decimal::add($layer->remaining_quantity_base, $take),
                        ])->save();
                        $layerId = $layer->id;
                    } else {
                        $stockRestoration = $returnItem->stockRestorations()
                            ->with('stockMovement')
                            ->orderBy('id')
                            ->first();

                        if (! $stockRestoration?->stockMovement) {
                            throw new DomainException('Fallback cost restoration requires a return stock movement.');
                        }

                        $layer = InventoryCostLayer::create([
                            'product_id' => $saleItem->product_id,
                            'product_batch_id' => $stockRestoration->product_batch_id,
                            'source_stock_movement_id' => $stockRestoration->stock_movement_id,
                            'initial_quantity_base' => $take,
                            'remaining_quantity_base' => $take,
                            'unit_cost_base' => $consumption->unit_cost_base,
                            'received_at' => $stockRestoration->stockMovement->occurred_at,
                        ]);
                        $layerId = $layer->id;
                    }
                }

                InventoryCostLayerRestoration::create([
                    'sale_return_item_id' => $returnItem->id,
                    'original_consumption_id' => $consumption->id,
                    'inventory_cost_layer_id' => $layerId,
                    'quantity_base' => $take,
                    'unit_cost_base' => $consumption->unit_cost_base,
                    'cost_amount' => $cost,
                ]);

                $totalCost = Decimal::add($totalCost, $cost, 4);
                $remaining = Decimal::subtract($remaining, $take);
            }

            if (Decimal::isPositive($remaining)) {
                throw new DomainException('Return cost restoration exceeds the sale item cost history.');
            }

            return Decimal::round($totalCost, 2);
        });
    }
}
