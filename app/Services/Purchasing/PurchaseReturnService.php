<?php

namespace App\Services\Purchasing;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Closing\BusinessDayService;
use App\Services\Documents\DocumentNumberService;
use App\Services\Inventory\InventoryCostService;
use App\Services\Inventory\InventoryService;
use App\Services\Suppliers\SupplierLedgerService;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseReturnService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly InventoryService $inventory,
        private readonly InventoryCostService $costing,
        private readonly SupplierLedgerService $ledger,
        private readonly BusinessDayService $days,
        private readonly AuditLogger $audit,
    ) {
    }

    public function post(GoodsReceipt $receipt, array $data, User $actor): PurchaseReturn
    {
        if (! $actor->hasPermission('purchases.return')) {
            throw new DomainException('The user is not allowed to return purchases.');
        }

        return DB::transaction(function () use ($receipt, $data, $actor): PurchaseReturn {
            if ($existing = PurchaseReturn::query()
                ->with(['items.goodsReceiptItem', 'supplier'])
                ->where('idempotency_key', $data['idempotency_key'])
                ->first()) {
                if ((int) $existing->goods_receipt_id !== (int) $receipt->id) {
                    throw new DomainException('The purchase return idempotency key belongs to another goods receipt.');
                }

                $this->assertRetryMatches($existing, $data);

                return $existing;
            }

            $this->days->lockOpen(now());

            $lockedReceipt = GoodsReceipt::query()
                ->with([
                    'supplier',
                    'items.product',
                    'items.productUnit.unit',
                    'items.stockMovement.batch',
                    'items.purchaseReturnItems',
                ])
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            $reason = trim((string) ($data['reason'] ?? ''));

            if ($reason === '') {
                throw new DomainException('A purchase return reason is required.');
            }

            $preparedItems = $this->prepareItems($lockedReceipt, $data['items'] ?? []);

            if ($preparedItems->isEmpty()) {
                throw new DomainException('A purchase return requires at least one item.');
            }

            $returnTotal = '0.00';

            foreach ($preparedItems as $prepared) {
                $returnTotal = Decimal::add($returnTotal, $prepared['return_amount'], 2);
            }

            $purchaseReturn = PurchaseReturn::create([
                'number' => $this->numbers->next('purchase_return', 'PRT'),
                'idempotency_key' => $data['idempotency_key'],
                'goods_receipt_id' => $lockedReceipt->id,
                'supplier_id' => $lockedReceipt->supplier_id,
                'created_by_user_id' => $actor->id,
                'reason' => $reason,
                'return_total' => $returnTotal,
                'posted_at' => now(),
            ]);

            foreach ($preparedItems as $prepared) {
                /** @var GoodsReceiptItem $receiptItem */
                $receiptItem = $prepared['receipt_item'];

                $cost = $this->costing->removeForPurchaseReturn(
                    receiptItem: $receiptItem,
                    quantityBase: $prepared['quantity_base'],
                );

                $movement = $this->inventory->returnPurchaseStock(
                    receiptItem: $receiptItem,
                    purchaseReturn: $purchaseReturn,
                    quantityBase: $prepared['quantity_base'],
                    actor: $actor,
                    notes: 'Purchase return '.$purchaseReturn->number.' from '.$lockedReceipt->number,
                );

                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'goods_receipt_item_id' => $receiptItem->id,
                    'inventory_cost_layer_id' => $cost['layer']->id,
                    'stock_movement_id' => $movement->id,
                    'quantity' => $prepared['quantity'],
                    'quantity_base' => $prepared['quantity_base'],
                    'return_amount' => $prepared['return_amount'],
                    'unit_cost_base' => $cost['unit_cost_base'],
                    'cost_amount' => $cost['cost_amount'],
                ]);
            }

            /** @var Supplier $supplier */
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($lockedReceipt->supplier_id);

            $this->ledger->debit(
                supplier: $supplier,
                amount: $returnTotal,
                entryType: 'purchase_return',
                referenceType: 'purchase_return',
                referenceId: $purchaseReturn->id,
                referenceNumber: $purchaseReturn->number,
                actor: $actor,
                notes: $reason,
                occurredAt: $purchaseReturn->posted_at,
            );

            $newReturnedTotal = Decimal::add(
                $lockedReceipt->returned_total,
                $returnTotal,
                2,
            );
            $rawBalance = Decimal::subtract(
                Decimal::subtract($lockedReceipt->net_total, $lockedReceipt->paid_amount, 2),
                $newReturnedTotal,
                2,
            );
            $newBalanceDue = Decimal::isNegative($rawBalance) ? '0.00' : $rawBalance;

            $lockedReceipt->forceFill([
                'returned_total' => $newReturnedTotal,
                'balance_due' => $newBalanceDue,
            ])->save();

            $this->audit->record(
                'purchasing.purchase_return.posted',
                model: $purchaseReturn,
                newValues: [
                    'goods_receipt_id' => $lockedReceipt->id,
                    'supplier_id' => $lockedReceipt->supplier_id,
                    'return_total' => $returnTotal,
                    'supplier_balance_after' => $supplier->fresh()->current_balance,
                ],
                actor: $actor,
            );

            return $purchaseReturn->fresh([
                'goodsReceipt',
                'supplier',
                'items.goodsReceiptItem.product',
                'items.stockMovement',
                'items.costLayer',
            ]);
        });
    }

    private function prepareItems(GoodsReceipt $receipt, array $items): Collection
    {
        if ($items === []) {
            throw new DomainException('A purchase return requires at least one item.');
        }

        $prepared = collect();
        $seen = [];

        foreach ($items as $input) {
            $receiptItemId = (int) ($input['goods_receipt_item_id'] ?? 0);

            if ($receiptItemId <= 0 || isset($seen[$receiptItemId])) {
                throw new DomainException('Each returned goods receipt item must be unique.');
            }

            $seen[$receiptItemId] = true;
            /** @var GoodsReceiptItem|null $receiptItem */
            $receiptItem = $receipt->items->firstWhere('id', $receiptItemId);

            if (! $receiptItem) {
                throw new DomainException('The selected return item does not belong to this goods receipt.');
            }

            $quantity = Decimal::normalize($input['quantity']);

            if (! Decimal::isPositive($quantity)) {
                throw new DomainException('Purchase return quantity must be greater than zero.');
            }

            if (Decimal::fractionalDigits($input['quantity']) > $receiptItem->productUnit->unit->decimal_places) {
                throw new DomainException('Purchase return quantity exceeds the selected unit precision.');
            }

            $alreadyReturnedQty = '0.000000';

            foreach ($receiptItem->purchaseReturnItems as $priorReturnItem) {
                $alreadyReturnedQty = Decimal::add(
                    $alreadyReturnedQty,
                    $priorReturnItem->quantity,
                );
            }

            $remainingQty = Decimal::subtract($receiptItem->quantity, $alreadyReturnedQty);

            if (Decimal::compare($quantity, $remainingQty) > 0) {
                throw new DomainException('Purchase return quantity exceeds the remaining returnable receipt quantity.');
            }

            $alreadyReturnedAmount = '0.00';

            foreach ($receiptItem->purchaseReturnItems as $priorReturnItem) {
                $alreadyReturnedAmount = Decimal::add(
                    $alreadyReturnedAmount,
                    $priorReturnItem->return_amount,
                    2,
                );
            }

            $remainingAmount = Decimal::subtract(
                $receiptItem->landed_total,
                $alreadyReturnedAmount,
                2,
            );

            $returnAmount = Decimal::compare($quantity, $remainingQty) === 0
                ? $remainingAmount
                : Decimal::multiplyRounded(
                    $quantity,
                    Decimal::divide($receiptItem->landed_total, $receiptItem->quantity, 6),
                    2,
                );

            if (Decimal::compare($returnAmount, $remainingAmount) > 0) {
                $returnAmount = $remainingAmount;
            }

            $prepared->push([
                'receipt_item' => $receiptItem,
                'quantity' => $quantity,
                'quantity_base' => Decimal::multiply($quantity, $receiptItem->conversion_factor),
                'return_amount' => $returnAmount,
            ]);
        }

        return $prepared;
    }

    private function assertRetryMatches(PurchaseReturn $existing, array $data): void
    {
        if (trim((string) ($data['reason'] ?? '')) !== $existing->reason) {
            throw new DomainException('The purchase return idempotency key is already bound to another reason.');
        }

        $requested = collect($data['items'] ?? [])
            ->map(fn (array $item) => [
                'goods_receipt_item_id' => (int) $item['goods_receipt_item_id'],
                'quantity' => Decimal::normalize($item['quantity']),
            ])
            ->sortBy('goods_receipt_item_id')
            ->values();

        $recorded = $existing->items
            ->map(fn (PurchaseReturnItem $item) => [
                'goods_receipt_item_id' => (int) $item->goods_receipt_item_id,
                'quantity' => Decimal::normalize($item->quantity),
            ])
            ->sortBy('goods_receipt_item_id')
            ->values();

        if ($requested->all() !== $recorded->all()) {
            throw new DomainException('The purchase return idempotency key is already bound to another item set.');
        }
    }
}
