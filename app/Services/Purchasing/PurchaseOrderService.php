<?php

namespace App\Services\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentNumberService;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly AuditLogger $audit,
    ) {
    }

    public function create(array $data, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $actor): PurchaseOrder {
            if (! Supplier::query()->whereKey($data['supplier_id'])->where('is_active', true)->exists()) {
                throw new DomainException('The selected supplier is not active.');
            }

            $items = $this->prepareItems($data['items']);
            $subtotal = '0.00';
            $lineDiscountTotal = '0.00';
            $lineNetTotal = '0.00';

            foreach ($items as $item) {
                $subtotal = Decimal::add($subtotal, $item['line_subtotal'], 2);
                $lineDiscountTotal = Decimal::add($lineDiscountTotal, $item['line_discount_amount'], 2);
                $lineNetTotal = Decimal::add($lineNetTotal, $item['line_net_total'], 2);
            }

            $orderDiscount = Decimal::normalize($data['order_discount_amount'] ?? '0', 2);

            if (Decimal::isNegative($orderDiscount) || Decimal::compare($orderDiscount, $lineNetTotal) > 0) {
                throw new DomainException('Purchase-order discount is invalid.');
            }

            $order = PurchaseOrder::create([
                'number' => $this->numbers->next('purchase_order', 'PO'),
                'supplier_id' => $data['supplier_id'],
                'created_by_user_id' => $actor->id,
                'status' => PurchaseOrderStatus::Draft,
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'subtotal' => $subtotal,
                'line_discount_total' => $lineDiscountTotal,
                'order_discount_amount' => $orderDiscount,
                'net_total' => Decimal::subtract($lineNetTotal, $orderDiscount, 2),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $order->items()->create($item);
            }

            $this->audit->record(
                'purchasing.order.created',
                model: $order,
                newValues: [
                    'number' => $order->number,
                    'supplier_id' => $order->supplier_id,
                    'net_total' => $order->net_total,
                ],
                actor: $actor,
            );

            return $order->fresh(['supplier', 'items.product', 'items.productUnit.unit']);
        });
    }

    public function approve(PurchaseOrder $order, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $actor): PurchaseOrder {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== PurchaseOrderStatus::Draft) {
                throw new DomainException('Only draft purchase orders can be approved.');
            }

            $locked->forceFill([
                'status' => PurchaseOrderStatus::Approved,
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
            ])->save();

            $this->audit->record(
                'purchasing.order.approved',
                model: $locked,
                newValues: ['status' => $locked->status->value],
                actor: $actor,
            );

            return $locked->fresh();
        });
    }

    public function cancel(PurchaseOrder $order, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $actor): PurchaseOrder {
            $locked = PurchaseOrder::query()->with('items')->lockForUpdate()->findOrFail($order->id);

            if (! in_array($locked->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Approved], true)) {
                throw new DomainException('This purchase order cannot be cancelled.');
            }

            if ($locked->items->contains(fn (PurchaseOrderItem $item) => Decimal::isPositive($item->received_quantity))) {
                throw new DomainException('A purchase order with received stock cannot be cancelled.');
            }

            $locked->forceFill([
                'status' => PurchaseOrderStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();

            $this->audit->record(
                'purchasing.order.cancelled',
                model: $locked,
                newValues: ['status' => $locked->status->value],
                actor: $actor,
            );

            return $locked->fresh();
        });
    }

    private function prepareItems(array $items): array
    {
        if ($items === []) {
            throw new DomainException('A purchase order requires at least one item.');
        }

        $prepared = [];
        $seen = [];

        foreach ($items as $item) {
            $productUnit = ProductUnit::query()
                ->with(['product', 'unit'])
                ->whereKey($item['product_unit_id'])
                ->where('can_purchase', true)
                ->whereHas('product', fn ($query) => $query->where('is_active', true))
                ->firstOrFail();

            $identity = $productUnit->product_id.':'.$productUnit->id;

            if (isset($seen[$identity])) {
                throw new DomainException('The same product unit cannot appear twice on one purchase order.');
            }

            $seen[$identity] = true;
            $quantity = Decimal::normalize($item['quantity']);

            if (! Decimal::isPositive($quantity)) {
                throw new DomainException('Purchase-order quantity must be greater than zero.');
            }

            if (Decimal::fractionalDigits($item['quantity']) > $productUnit->unit->decimal_places) {
                throw new DomainException('Purchase-order quantity exceeds the selected unit precision.');
            }

            $unitCost = Decimal::normalize($item['unit_cost'], 4);

            if (Decimal::isNegative($unitCost)) {
                throw new DomainException('Unit cost cannot be negative.');
            }

            $lineSubtotal = Decimal::multiplyRounded($quantity, $unitCost, 2);
            $lineDiscount = Decimal::normalize($item['line_discount_amount'] ?? '0', 2);

            if (Decimal::isNegative($lineDiscount) || Decimal::compare($lineDiscount, $lineSubtotal) > 0) {
                throw new DomainException('Purchase-order line discount is invalid.');
            }

            $prepared[] = [
                'product_id' => $productUnit->product_id,
                'product_unit_id' => $productUnit->id,
                'ordered_quantity' => $quantity,
                'received_quantity' => '0.000000',
                'unit_cost' => $unitCost,
                'line_subtotal' => $lineSubtotal,
                'line_discount_amount' => $lineDiscount,
                'line_net_total' => Decimal::subtract($lineSubtotal, $lineDiscount, 2),
                'notes' => $item['notes'] ?? null,
            ];
        }

        return $prepared;
    }
}
