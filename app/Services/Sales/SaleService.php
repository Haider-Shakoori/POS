<?php

namespace App\Services\Sales;

use App\Enums\SalePaymentStatus;
use App\Enums\SaleStatus;
use App\Models\CashierShift;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemStockAllocation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentNumberService;
use App\Services\Inventory\InventoryCostService;
use App\Services\Inventory\InventoryService;
use App\Services\Purchasing\ProportionalAllocator;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly SalesPricingService $pricing,
        private readonly ProportionalAllocator $allocator,
        private readonly InventoryService $inventory,
        private readonly InventoryCostService $costing,
        private readonly AuditLogger $audit,
    ) {
    }

    public function complete(array $data, User $actor): Sale
    {
        return DB::transaction(function () use ($data, $actor): Sale {
            if (! $actor->hasPermission('sales.create')) {
                throw new DomainException('The user is not allowed to create sales.');
            }

            if ($existing = Sale::query()->where('idempotency_key', $data['idempotency_key'])->first()) {
                if ((int) $existing->cashier_user_id !== (int) $actor->id) {
                    throw new DomainException('The idempotency key is already bound to another sale.');
                }

                return $existing->load([
                    'cashier',
                    'items.product',
                    'items.productUnit.unit',
                    'items.stockAllocations.batch',
                    'items.costConsumptions.layer',
                ]);
            }

            $preparedItems = $this->prepareItems($data['items'], $actor);
            $subtotal = '0.00';
            $lineDiscountTotal = '0.00';
            $lineBasisTotal = '0.00';

            foreach ($preparedItems as $item) {
                $subtotal = Decimal::add($subtotal, $item['line_subtotal'], 2);
                $lineDiscountTotal = Decimal::add($lineDiscountTotal, $item['line_discount_amount'], 2);
                $lineBasisTotal = Decimal::add($lineBasisTotal, $item['basis_after_line_discount'], 2);
            }

            $saleDiscount = Decimal::normalize($data['sale_discount_amount'] ?? '0', 2);

            if (Decimal::isNegative($saleDiscount) || Decimal::compare($saleDiscount, $lineBasisTotal) > 0) {
                throw new DomainException('Sale discount is invalid.');
            }

            if (Decimal::isPositive($saleDiscount) && ! $actor->hasPermission('sales.discount')) {
                throw new DomainException('The user is not allowed to apply a sale discount.');
            }

            $saleDiscountAllocations = $this->allocator->allocate(
                $saleDiscount,
                array_column($preparedItems, 'basis_after_line_discount'),
            );

            $netTotal = Decimal::subtract($lineBasisTotal, $saleDiscount, 2);

            foreach ($preparedItems as $index => &$item) {
                $item['allocated_sale_discount'] = $saleDiscountAllocations[$index] ?? '0.00';
                $item['line_net_total'] = Decimal::subtract(
                    $item['basis_after_line_discount'],
                    $item['allocated_sale_discount'],
                    2,
                );

                if ($item['minimum_unit_price'] !== null) {
                    $effectiveUnitPrice = Decimal::divide(
                        $item['line_net_total'],
                        $item['quantity'],
                        4,
                    );

                    if (
                        Decimal::compare($effectiveUnitPrice, $item['minimum_unit_price']) < 0
                        && ! $actor->hasPermission('sales.override_min_price')
                    ) {
                        throw new DomainException(
                            "The final price for {$item['product']->name_en} is below its configured minimum price."
                        );
                    }
                }
            }
            unset($item);

            $openShift = CashierShift::query()
                ->where('user_id', $actor->id)
                ->where('status', 'open')
                ->latest('opened_at')
                ->first();

            $sale = Sale::create([
                'number' => $this->numbers->next('sale', 'SAL'),
                'idempotency_key' => $data['idempotency_key'],
                'cashier_user_id' => $actor->id,
                'terminal_id' => $openShift?->terminal_id,
                'cashier_shift_id' => $openShift?->id,
                'status' => SaleStatus::Completed,
                'payment_status' => SalePaymentStatus::Unpaid,
                'customer_name_snapshot' => 'Walk-in Customer',
                'subtotal' => $subtotal,
                'line_discount_total' => $lineDiscountTotal,
                'sale_discount_amount' => $saleDiscount,
                'net_total' => $netTotal,
                'cogs_total' => '0.00',
                'gross_profit' => $netTotal,
                'paid_amount' => '0.00',
                'balance_due' => $netTotal,
                'sold_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $cogsTotal = '0.00';

            foreach ($preparedItems as $item) {
                $saleItem = $sale->items()->create([
                    'product_id' => $item['product']->id,
                    'product_unit_id' => $item['product_unit']->id,
                    'product_name_snapshot' => $item['product']->localizedName(),
                    'sku_snapshot' => $item['product']->sku,
                    'unit_name_snapshot' => $item['product_unit']->unit->localizedName(),
                    'quantity' => $item['quantity'],
                    'conversion_factor' => $item['product_unit']->conversion_factor,
                    'quantity_base' => $item['quantity_base'],
                    'unit_price' => $item['unit_price'],
                    'minimum_unit_price' => $item['minimum_unit_price'],
                    'line_subtotal' => $item['line_subtotal'],
                    'line_discount_amount' => $item['line_discount_amount'],
                    'allocated_sale_discount' => $item['allocated_sale_discount'],
                    'line_net_total' => $item['line_net_total'],
                    'cogs_amount' => '0.00',
                    'gross_profit' => $item['line_net_total'],
                ]);

                $stockAllocations = $this->inventory->deductSaleStock(
                    productUnit: $item['product_unit'],
                    sourceQuantity: $item['quantity'],
                    actor: $actor,
                    referenceType: SaleItem::class,
                    referenceId: $saleItem->id,
                    notes: 'Sale '.$sale->number,
                );

                foreach ($stockAllocations as $allocation) {
                    SaleItemStockAllocation::create([
                        'sale_item_id' => $saleItem->id,
                        'product_batch_id' => $allocation['batch_id'],
                        'stock_movement_id' => $allocation['movement']->id,
                        'quantity_base' => $allocation['quantity_base'],
                    ]);
                }

                $cogs = $this->costing->consumeForSaleItem(
                    saleItem: $saleItem,
                    product: $item['product'],
                    quantityBase: $item['quantity_base'],
                );
                $grossProfit = Decimal::subtract($item['line_net_total'], $cogs, 2);

                DB::table('sale_items')
                    ->where('id', $saleItem->id)
                    ->update([
                        'cogs_amount' => $cogs,
                        'gross_profit' => $grossProfit,
                        'updated_at' => now(),
                    ]);

                $cogsTotal = Decimal::add($cogsTotal, $cogs, 2);
            }

            $grossProfit = Decimal::subtract($netTotal, $cogsTotal, 2);

            DB::table('sales')
                ->where('id', $sale->id)
                ->update([
                    'cogs_total' => $cogsTotal,
                    'gross_profit' => $grossProfit,
                    'updated_at' => now(),
                ]);

            $this->audit->record(
                'sales.sale.completed',
                model: $sale,
                newValues: [
                    'number' => $sale->number,
                    'net_total' => $netTotal,
                    'cogs_total' => $cogsTotal,
                    'gross_profit' => $grossProfit,
                    'balance_due' => $netTotal,
                ],
                actor: $actor,
            );

            return $sale->fresh([
                'cashier',
                'items.product',
                'items.productUnit.unit',
                'items.stockAllocations.batch',
                'items.stockAllocations.stockMovement',
                'items.costConsumptions.layer',
            ]);
        });
    }

    private function prepareItems(array $items, User $actor): array
    {
        if ($items === []) {
            throw new DomainException('A sale requires at least one item.');
        }

        $prepared = [];
        $seen = [];

        foreach ($items as $item) {
            $productUnitId = (int) $item['product_unit_id'];

            if (isset($seen[$productUnitId])) {
                throw new DomainException('The same product unit cannot appear twice on one sale.');
            }

            $seen[$productUnitId] = true;

            $productUnit = ProductUnit::query()
                ->with(['product', 'unit'])
                ->whereKey($productUnitId)
                ->where('can_sell', true)
                ->whereHas('product', fn ($query) => $query->where('is_active', true))
                ->firstOrFail();

            $quantity = Decimal::normalize($item['quantity']);

            if (! Decimal::isPositive($quantity)) {
                throw new DomainException('Sale quantity must be greater than zero.');
            }

            if (Decimal::fractionalDigits($item['quantity']) > $productUnit->unit->decimal_places) {
                throw new DomainException('Sale quantity exceeds the selected unit precision.');
            }

            $prices = $this->pricing->resolve($productUnit);
            $lineSubtotal = Decimal::multiplyRounded($quantity, $prices['price'], 2);
            $lineDiscount = Decimal::normalize($item['line_discount_amount'] ?? '0', 2);

            if (Decimal::isNegative($lineDiscount) || Decimal::compare($lineDiscount, $lineSubtotal) > 0) {
                throw new DomainException('Sale line discount is invalid.');
            }

            if (Decimal::isPositive($lineDiscount) && ! $actor->hasPermission('sales.discount')) {
                throw new DomainException('The user is not allowed to apply a line discount.');
            }

            $prepared[] = [
                'product' => $productUnit->product,
                'product_unit' => $productUnit,
                'quantity' => $quantity,
                'quantity_base' => Decimal::multiply($quantity, $productUnit->conversion_factor),
                'unit_price' => $prices['price'],
                'minimum_unit_price' => $prices['minimum_price'],
                'line_subtotal' => $lineSubtotal,
                'line_discount_amount' => $lineDiscount,
                'basis_after_line_discount' => Decimal::subtract($lineSubtotal, $lineDiscount, 2),
            ];
        }

        return $prepared;
    }
}
