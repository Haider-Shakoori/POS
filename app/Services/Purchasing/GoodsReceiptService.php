<?php

namespace App\Services\Purchasing;

use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseExpenseType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchasePaymentMethod;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Cash\CashMovementService;
use App\Services\Closing\BusinessDayService;
use App\Services\Documents\DocumentNumberService;
use App\Services\Inventory\InventoryService;
use App\Services\Suppliers\SupplierLedgerService;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoodsReceiptService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly ProportionalAllocator $allocator,
        private readonly InventoryService $inventory,
        private readonly SupplierLedgerService $supplierLedger,
        private readonly CashMovementService $cash,
        private readonly BusinessDayService $days,
        private readonly AuditLogger $audit,
    ) {
    }

    public function post(array $data, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $actor): GoodsReceipt {
            if (! $actor->hasPermission('purchases.receive')) {
                throw new DomainException('The user is not allowed to receive purchases.');
            }

            $requestedOrderId = ! empty($data['purchase_order_id'])
                ? (int) $data['purchase_order_id']
                : null;

            if ($existing = GoodsReceipt::query()->where('idempotency_key', $data['idempotency_key'])->first()) {
                if (
                    (int) $existing->supplier_id !== (int) $data['supplier_id']
                    || ($existing->purchase_order_id ? (int) $existing->purchase_order_id : null) !== $requestedOrderId
                ) {
                    throw new DomainException('The idempotency key is already bound to another goods receipt.');
                }

                return $existing->load(['supplier', 'purchaseOrder', 'items.product', 'expenses', 'payments']);
            }

            $receivedAt = ! empty($data['received_at'])
                ? CarbonImmutable::parse($data['received_at'])
                : now();

            $this->days->lockOpen($receivedAt);

            if (! Supplier::query()->whereKey($data['supplier_id'])->where('is_active', true)->exists()) {
                throw new DomainException('The selected supplier is not active.');
            }

            $order = null;

            if ($requestedOrderId) {
                $order = PurchaseOrder::query()
                    ->with(['items.product', 'items.productUnit.unit'])
                    ->lockForUpdate()
                    ->findOrFail($requestedOrderId);

                if (! $order->isReceivable()) {
                    throw new DomainException('The purchase order is not open for receiving.');
                }

                if ((int) $order->supplier_id !== (int) $data['supplier_id']) {
                    throw new DomainException('Goods receipt supplier must match the purchase order supplier.');
                }
            } elseif (! $actor->hasPermission('purchases.direct_receive')) {
                throw new DomainException('The user is not allowed to post direct goods receipts.');
            }

            $preparedItems = $this->prepareItems($data['items'], $order);
            $subtotal = '0.00';
            $lineDiscountTotal = '0.00';
            $basisTotal = '0.00';

            foreach ($preparedItems as $item) {
                $subtotal = Decimal::add($subtotal, $item['line_subtotal'], 2);
                $lineDiscountTotal = Decimal::add($lineDiscountTotal, $item['line_discount_amount'], 2);
                $basisTotal = Decimal::add($basisTotal, $item['basis_after_line_discount'], 2);
            }

            $receiptDiscount = Decimal::normalize($data['receipt_discount_amount'] ?? '0', 2);

            if (Decimal::isNegative($receiptDiscount) || Decimal::compare($receiptDiscount, $basisTotal) > 0) {
                throw new DomainException('Receipt discount is invalid.');
            }

            $expenses = $this->prepareExpenses($data['expenses'] ?? []);
            $expenseTotal = '0.00';

            foreach ($expenses as $expense) {
                $expenseTotal = Decimal::add($expenseTotal, $expense['amount'], 2);
            }

            $discountAllocations = $this->allocator->allocate(
                $receiptDiscount,
                array_column($preparedItems, 'basis_after_line_discount'),
            );

            $expenseWeights = [];

            foreach ($preparedItems as $index => &$item) {
                $item['allocated_receipt_discount'] = $discountAllocations[$index] ?? '0.00';
                $item['discounted_basis'] = Decimal::subtract(
                    $item['basis_after_line_discount'],
                    $item['allocated_receipt_discount'],
                    2,
                );
                $expenseWeights[] = $item['discounted_basis'];
            }
            unset($item);

            if (! collect($expenseWeights)->contains(fn (string $weight) => Decimal::isPositive($weight))) {
                $expenseWeights = array_column($preparedItems, 'quantity_base');
            }

            $expenseAllocations = $this->allocator->allocate($expenseTotal, $expenseWeights);
            $netTotal = Decimal::add(
                Decimal::subtract($basisTotal, $receiptDiscount, 2),
                $expenseTotal,
                2,
            );
            $paidAmount = Decimal::normalize($data['paid_amount'] ?? '0', 2);

            if (Decimal::isNegative($paidAmount) || Decimal::compare($paidAmount, $netTotal) > 0) {
                throw new DomainException('Paid amount cannot exceed the goods receipt total.');
            }

            if (Decimal::isPositive($paidAmount)) {
                if (! $actor->hasPermission('purchases.record_payment')) {
                    throw new DomainException('The user is not allowed to record a purchase payment.');
                }

                if (empty($data['payment_method']) || ! PurchasePaymentMethod::tryFrom((string) $data['payment_method'])) {
                    throw new DomainException('A valid payment method is required when payment is recorded.');
                }
            }

            $receipt = GoodsReceipt::create([
                'number' => $this->numbers->next('goods_receipt', 'GRN'),
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $order?->id,
                'created_by_user_id' => $actor->id,
                'posted_by_user_id' => $actor->id,
                'status' => GoodsReceiptStatus::Posted,
                'idempotency_key' => $data['idempotency_key'],
                'supplier_invoice_reference' => $data['supplier_invoice_reference'] ?? null,
                'received_at' => $receivedAt,
                'subtotal' => $subtotal,
                'line_discount_total' => $lineDiscountTotal,
                'receipt_discount_amount' => $receiptDiscount,
                'expense_total' => $expenseTotal,
                'net_total' => $netTotal,
                'paid_amount' => $paidAmount,
                'balance_due' => Decimal::subtract($netTotal, $paidAmount, 2),
                'posted_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($expenses as $expense) {
                $receipt->expenses()->create($expense);
            }

            foreach ($preparedItems as $index => $item) {
                $allocatedExpense = $expenseAllocations[$index] ?? '0.00';
                $landedTotal = Decimal::add($item['discounted_basis'], $allocatedExpense, 2);
                $sourceUnitLandedCost = Decimal::divide($landedTotal, $item['quantity'], 4);
                $baseUnitLandedCost = Decimal::divide($landedTotal, $item['quantity_base'], 4);

                $receiptItem = $receipt->items()->create([
                    'purchase_order_item_id' => $item['purchase_order_item_id'],
                    'product_id' => $item['product_id'],
                    'product_unit_id' => $item['product_unit_id'],
                    'stock_movement_key' => (string) Str::uuid(),
                    'quantity' => $item['quantity'],
                    'conversion_factor' => $item['conversion_factor'],
                    'quantity_base' => $item['quantity_base'],
                    'source_unit_cost' => $item['source_unit_cost'],
                    'line_subtotal' => $item['line_subtotal'],
                    'line_discount_amount' => $item['line_discount_amount'],
                    'allocated_receipt_discount' => $item['allocated_receipt_discount'],
                    'allocated_expense' => $allocatedExpense,
                    'landed_total' => $landedTotal,
                    'source_unit_landed_cost' => $sourceUnitLandedCost,
                    'base_unit_landed_cost' => $baseUnitLandedCost,
                    'batch_number' => $item['batch_number'],
                    'manufactured_at' => $item['manufactured_at'],
                    'expires_at' => $item['expires_at'],
                ]);

                $movement = $this->inventory->receivePurchaseStock(
                    productUnit: $item['product_unit'],
                    sourceQuantity: $item['quantity'],
                    batchData: $item['batch_number'] ? [
                        'batch_number' => $item['batch_number'],
                        'manufactured_at' => $item['manufactured_at'],
                        'expires_at' => $item['expires_at'],
                        'notes' => 'Received on '.$receipt->number,
                    ] : null,
                    sourceUnitLandedCost: $sourceUnitLandedCost,
                    baseUnitLandedCost: $baseUnitLandedCost,
                    actor: $actor,
                    referenceType: GoodsReceiptItem::class,
                    referenceId: $receiptItem->id,
                    idempotencyKey: $receiptItem->stock_movement_key,
                    notes: 'Goods receipt '.$receipt->number,
                );

                DB::table('goods_receipt_items')
                    ->where('id', $receiptItem->id)
                    ->update(['stock_movement_id' => $movement->id]);

                if ($item['purchase_order_item']) {
                    $poItem = PurchaseOrderItem::query()
                        ->lockForUpdate()
                        ->findOrFail($item['purchase_order_item']->id);

                    $newReceived = Decimal::add($poItem->received_quantity, $item['quantity']);

                    if (Decimal::compare($newReceived, $poItem->ordered_quantity) > 0) {
                        throw new DomainException('Received quantity exceeds the remaining purchase-order quantity.');
                    }

                    $poItem->forceFill(['received_quantity' => $newReceived])->save();
                }
            }

            $initialPayment = null;

            if (Decimal::isPositive($paidAmount)) {
                $initialPayment = PurchasePayment::create([
                    'goods_receipt_id' => $receipt->id,
                    'supplier_id' => $receipt->supplier_id,
                    'recorded_by_user_id' => $actor->id,
                    'amount' => $paidAmount,
                    'method' => $data['payment_method'],
                    'reference' => $data['payment_reference'] ?? null,
                    'paid_at' => $receivedAt,
                    'notes' => $data['payment_notes'] ?? null,
                ]);
            }

            $supplier = Supplier::query()->findOrFail($receipt->supplier_id);

            if (Decimal::isPositive($receipt->net_total)) {
                $this->supplierLedger->credit(
                    supplier: $supplier,
                    amount: $receipt->net_total,
                    entryType: 'goods_receipt',
                    referenceType: 'goods_receipt',
                    referenceId: $receipt->id,
                    referenceNumber: $receipt->number,
                    actor: $actor,
                    notes: 'Posted goods receipt '.$receipt->number,
                    occurredAt: $receipt->received_at,
                );
            }

            if ($initialPayment && $initialPayment->method === PurchasePaymentMethod::Cash) {
                $this->cash->recordSource(
                    actor: $actor,
                    amount: $initialPayment->amount,
                    direction: 'outflow',
                    movementType: 'purchase_payment',
                    sourceType: 'purchase_payment',
                    sourceId: $initialPayment->id,
                    referenceNumber: $receipt->number,
                    reason: 'Initial cash purchase payment',
                    occurredAt: $initialPayment->paid_at,
                );
            }

            if ($initialPayment) {
                $this->supplierLedger->debit(
                    supplier: $supplier,
                    amount: $initialPayment->amount,
                    entryType: 'initial_purchase_payment',
                    referenceType: 'purchase_payment',
                    referenceId: $initialPayment->id,
                    referenceNumber: null,
                    actor: $actor,
                    notes: $initialPayment->notes,
                    occurredAt: $initialPayment->paid_at,
                );
            }

            if ($order) {
                $this->refreshOrderStatus($order);
            }

            $this->audit->record(
                'purchasing.receipt.posted',
                model: $receipt,
                newValues: [
                    'number' => $receipt->number,
                    'supplier_id' => $receipt->supplier_id,
                    'purchase_order_id' => $receipt->purchase_order_id,
                    'net_total' => $receipt->net_total,
                    'paid_amount' => $receipt->paid_amount,
                    'balance_due' => $receipt->balance_due,
                ],
                actor: $actor,
            );

            return $receipt->fresh([
                'supplier',
                'purchaseOrder',
                'items.product',
                'items.productUnit.unit',
                'items.stockMovement',
                'expenses',
                'payments',
            ]);
        });
    }

    private function prepareItems(array $items, ?PurchaseOrder $order): array
    {
        if ($items === []) {
            throw new DomainException('A goods receipt requires at least one item.');
        }

        $prepared = [];
        $seenPoItems = [];

        foreach ($items as $item) {
            $poItem = null;

            if ($order) {
                if (empty($item['purchase_order_item_id'])) {
                    throw new DomainException('Each receipt line must reference a purchase-order item.');
                }

                if (isset($seenPoItems[$item['purchase_order_item_id']])) {
                    throw new DomainException('A purchase-order item cannot appear twice on one receipt.');
                }

                $seenPoItems[$item['purchase_order_item_id']] = true;
                $poItem = $order->items->firstWhere('id', (int) $item['purchase_order_item_id']);

                if (! $poItem) {
                    throw new DomainException('Receipt line does not belong to the selected purchase order.');
                }

                $productUnit = $poItem->productUnit;
            } else {
                if (! array_key_exists('unit_cost', $item) || $item['unit_cost'] === null || $item['unit_cost'] === '') {
                    throw new DomainException('Direct receipt lines require an explicit unit cost.');
                }

                $productUnit = ProductUnit::query()
                    ->with(['product', 'unit'])
                    ->whereKey($item['product_unit_id'])
                    ->where('can_purchase', true)
                    ->whereHas('product', fn ($query) => $query->where('is_active', true))
                    ->firstOrFail();
            }

            $productUnit->loadMissing(['product', 'unit']);
            $quantity = Decimal::normalize($item['quantity']);

            if (! Decimal::isPositive($quantity)) {
                throw new DomainException('Received quantity must be greater than zero.');
            }

            if (Decimal::fractionalDigits($item['quantity']) > $productUnit->unit->decimal_places) {
                throw new DomainException('Received quantity exceeds the selected unit precision.');
            }

            if ($poItem) {
                $remaining = Decimal::subtract($poItem->ordered_quantity, $poItem->received_quantity);

                if (Decimal::compare($quantity, $remaining) > 0) {
                    throw new DomainException('Received quantity exceeds the remaining purchase-order quantity.');
                }
            }

            $unitCost = Decimal::normalize(
                $item['unit_cost'] ?? $poItem?->unit_cost ?? '0',
                4,
            );

            if (Decimal::isNegative($unitCost)) {
                throw new DomainException('Received unit cost cannot be negative.');
            }

            $lineSubtotal = Decimal::multiplyRounded($quantity, $unitCost, 2);
            $lineDiscount = Decimal::normalize($item['line_discount_amount'] ?? '0', 2);

            if (Decimal::isNegative($lineDiscount) || Decimal::compare($lineDiscount, $lineSubtotal) > 0) {
                throw new DomainException('Receipt line discount is invalid.');
            }

            if (
                ! empty($item['manufactured_at'])
                && ! empty($item['expires_at'])
                && CarbonImmutable::parse($item['expires_at'])->lt(CarbonImmutable::parse($item['manufactured_at']))
            ) {
                throw new DomainException('Expiry date cannot be before manufacture date.');
            }

            if ($productUnit->product->track_expiry) {
                if (empty($item['batch_number']) || empty($item['expires_at'])) {
                    throw new DomainException('Batch number and expiry date are required for expiry-tracked products.');
                }
            }

            $prepared[] = [
                'purchase_order_item' => $poItem,
                'purchase_order_item_id' => $poItem?->id,
                'product_unit' => $productUnit,
                'product_id' => $productUnit->product_id,
                'product_unit_id' => $productUnit->id,
                'quantity' => $quantity,
                'conversion_factor' => $productUnit->conversion_factor,
                'quantity_base' => Decimal::multiply($quantity, $productUnit->conversion_factor),
                'source_unit_cost' => $unitCost,
                'line_subtotal' => $lineSubtotal,
                'line_discount_amount' => $lineDiscount,
                'basis_after_line_discount' => Decimal::subtract($lineSubtotal, $lineDiscount, 2),
                'batch_number' => $item['batch_number'] ?? null,
                'manufactured_at' => $item['manufactured_at'] ?? null,
                'expires_at' => $item['expires_at'] ?? null,
            ];
        }

        return $prepared;
    }

    private function prepareExpenses(array $expenses): array
    {
        $prepared = [];

        foreach ($expenses as $expense) {
            if (empty($expense['type']) || ! PurchaseExpenseType::tryFrom((string) $expense['type'])) {
                throw new DomainException('Invalid purchase expense type.');
            }

            $amount = Decimal::normalize($expense['amount'], 2);

            if (! Decimal::isPositive($amount)) {
                throw new DomainException('Purchase expense amount must be greater than zero.');
            }

            $prepared[] = [
                'type' => $expense['type'],
                'description' => $expense['description'] ?? null,
                'amount' => $amount,
            ];
        }

        return $prepared;
    }

    private function refreshOrderStatus(PurchaseOrder $order): void
    {
        $order = PurchaseOrder::query()->with('items')->lockForUpdate()->findOrFail($order->id);
        $allReceived = $order->items->every(
            fn (PurchaseOrderItem $item) => Decimal::compare($item->received_quantity, $item->ordered_quantity) >= 0,
        );

        $order->forceFill([
            'status' => $allReceived
                ? PurchaseOrderStatus::Received
                : PurchaseOrderStatus::PartiallyReceived,
        ])->save();
    }
}
