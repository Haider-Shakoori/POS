<?php

namespace App\Services\Sales;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleRefund;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Customers\CustomerLedgerService;
use App\Services\Documents\DocumentNumberService;
use App\Services\Inventory\InventoryCostService;
use App\Services\Inventory\InventoryService;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SaleReturnService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly InventoryService $inventory,
        private readonly InventoryCostService $costing,
        private readonly CustomerLedgerService $ledger,
        private readonly AuditLogger $audit,
    ) {
    }

    public function returnItems(Sale $sale, array $data, User $actor): SaleReturn
    {
        if (! $actor->hasPermission('sales.return')) {
            throw new DomainException('The user is not allowed to return sales.');
        }

        return $this->post($sale, $data, $actor, 'return');
    }

    public function void(Sale $sale, array $data, User $actor): SaleReturn
    {
        if (! $actor->hasPermission('sales.void')) {
            throw new DomainException('The user is not allowed to void sales.');
        }

        return $this->post($sale, $data, $actor, 'void');
    }

    private function post(Sale $sale, array $data, User $actor, string $type): SaleReturn
    {
        return DB::transaction(function () use ($sale, $data, $actor, $type): SaleReturn {
            if ($existing = SaleReturn::query()
                ->with(['items', 'refunds'])
                ->where('idempotency_key', $data['idempotency_key'])
                ->first()) {
                if ((int) $existing->sale_id !== (int) $sale->id || $existing->type !== $type) {
                    throw new DomainException('The return idempotency key is already bound to another reversal.');
                }

                $this->assertRetryMatches($existing, $data, $type);

                return $existing->load([
                    'items.saleItem',
                    'items.stockRestorations',
                    'items.costRestorations',
                    'refunds.paymentMethod',
                ]);
            }

            $lockedSale = Sale::query()
                ->with([
                    'customer',
                    'items.product',
                    'items.productUnit.unit',
                    'items.stockAllocations.batch',
                    'items.costConsumptions',
                ])
                ->lockForUpdate()
                ->findOrFail($sale->id);

            if ($lockedSale->status === SaleStatus::Voided) {
                throw new DomainException('A voided sale cannot be reversed again.');
            }

            $reason = trim((string) ($data['reason'] ?? ''));

            if ($reason === '') {
                throw new DomainException('A return or void reason is required.');
            }

            $preparedItems = $type === 'void'
                ? $this->prepareVoidItems($lockedSale)
                : $this->prepareRequestedItems($lockedSale, $data['items'] ?? []);

            if ($preparedItems->isEmpty()) {
                throw new DomainException('No returnable sale quantity remains.');
            }

            $returnTotal = '0.00';

            foreach ($preparedItems as $item) {
                $returnTotal = Decimal::add($returnTotal, $item['return_amount'], 2);
            }

            $receivableReversed = '0.00';
            $refundDue = $returnTotal;

            if ($lockedSale->customer_id && Decimal::isPositive($lockedSale->balance_due)) {
                $receivableReversed = Decimal::compare($returnTotal, $lockedSale->balance_due) <= 0
                    ? $returnTotal
                    : $lockedSale->balance_due;
                $refundDue = Decimal::subtract($returnTotal, $receivableReversed, 2);
            }

            $availablePaidForRefund = Decimal::subtract(
                $lockedSale->paid_amount,
                $lockedSale->refunded_total,
                2,
            );

            if (Decimal::compare($refundDue, $availablePaidForRefund) > 0) {
                throw new DomainException('The reversal exceeds the refundable paid amount for this sale.');
            }

            $preparedRefunds = $this->prepareRefunds($data['refunds'] ?? [], $refundDue);

            $return = SaleReturn::create([
                'number' => $this->numbers->next('sale_return', $type === 'void' ? 'VOID' : 'RET'),
                'idempotency_key' => $data['idempotency_key'],
                'sale_id' => $lockedSale->id,
                'created_by_user_id' => $actor->id,
                'type' => $type,
                'status' => 'posted',
                'reason' => $reason,
                'return_total' => $returnTotal,
                'cogs_reversed' => '0.00',
                'receivable_reversed' => $receivableReversed,
                'refund_total' => $refundDue,
                'posted_at' => now(),
            ]);

            $cogsReversed = '0.00';

            foreach ($preparedItems as $prepared) {
                /** @var SaleItem $saleItem */
                $saleItem = $prepared['sale_item'];

                $returnItem = $return->items()->create([
                    'sale_item_id' => $saleItem->id,
                    'quantity' => $prepared['quantity'],
                    'quantity_base' => $prepared['quantity_base'],
                    'return_amount' => $prepared['return_amount'],
                    'cogs_amount' => '0.00',
                ]);

                $this->inventory->restoreSaleReturnStock(
                    returnItem: $returnItem,
                    saleItem: $saleItem,
                    quantityBase: $prepared['quantity_base'],
                    actor: $actor,
                    notes: ucfirst($type).' '.$return->number.' for sale '.$lockedSale->number,
                );

                $itemCogs = $this->costing->restoreForReturnItem(
                    returnItem: $returnItem,
                    saleItem: $saleItem,
                    quantityBase: $prepared['quantity_base'],
                );

                DB::table('sale_return_items')
                    ->where('id', $returnItem->id)
                    ->update([
                        'cogs_amount' => $itemCogs,
                        'updated_at' => now(),
                    ]);

                $cogsReversed = Decimal::add($cogsReversed, $itemCogs, 2);
            }

            if (Decimal::isPositive($receivableReversed)) {
                $customer = Customer::query()->lockForUpdate()->findOrFail($lockedSale->customer_id);

                $this->ledger->credit(
                    customer: $customer,
                    amount: $receivableReversed,
                    entryType: $type === 'void' ? 'sale_void' : 'sale_return',
                    referenceType: 'sale_return',
                    referenceId: $return->id,
                    referenceNumber: $return->number,
                    actor: $actor,
                    notes: ucfirst($type).' receivable reversal for '.$lockedSale->number,
                );
            }

            foreach ($preparedRefunds as $index => $refund) {
                SaleRefund::create([
                    'idempotency_key' => $return->idempotency_key.':refund:'.$index,
                    'sale_return_id' => $return->id,
                    'payment_method_id' => $refund['method']->id,
                    'recorded_by_user_id' => $actor->id,
                    'amount' => $refund['amount'],
                    'reference' => $refund['reference'],
                    'refunded_at' => now(),
                    'notes' => $refund['notes'],
                ]);
            }

            $newReturnedTotal = Decimal::add($lockedSale->returned_total, $returnTotal, 2);
            $newReceivableReversed = Decimal::add(
                $lockedSale->receivable_reversed_total,
                $receivableReversed,
                2,
            );
            $newRefundedTotal = Decimal::add($lockedSale->refunded_total, $refundDue, 2);
            $newBalanceDue = Decimal::subtract($lockedSale->balance_due, $receivableReversed, 2);

            $status = $type === 'void'
                ? SaleStatus::Voided
                : (
                    Decimal::compare($newReturnedTotal, $lockedSale->net_total) >= 0
                        ? SaleStatus::Returned
                        : SaleStatus::PartiallyReturned
                );

            $lockedSale->forceFill([
                'status' => $status,
                'balance_due' => $newBalanceDue,
                'returned_total' => $newReturnedTotal,
                'receivable_reversed_total' => $newReceivableReversed,
                'refunded_total' => $newRefundedTotal,
            ])->save();

            DB::table('sale_returns')
                ->where('id', $return->id)
                ->update([
                    'cogs_reversed' => $cogsReversed,
                    'updated_at' => now(),
                ]);

            $this->audit->record(
                $type === 'void' ? 'sales.sale.voided' : 'sales.sale.returned',
                model: $lockedSale,
                newValues: [
                    'return_id' => $return->id,
                    'return_number' => $return->number,
                    'return_total' => $returnTotal,
                    'cogs_reversed' => $cogsReversed,
                    'receivable_reversed' => $receivableReversed,
                    'refund_total' => $refundDue,
                    'status' => $status->value,
                ],
                actor: $actor,
            );

            return $return->fresh([
                'sale',
                'items.saleItem',
                'items.stockRestorations.stockMovement',
                'items.costRestorations.layer',
                'refunds.paymentMethod',
            ]);
        });
    }

    private function assertRetryMatches(SaleReturn $existing, array $data, string $type): void
    {
        if (trim((string) ($data['reason'] ?? '')) !== $existing->reason) {
            throw new DomainException('The return idempotency key is already bound to another reason.');
        }

        if ($type === 'return') {
            $requestedItems = collect($data['items'] ?? [])
                ->map(fn (array $item) => [
                    'sale_item_id' => (int) $item['sale_item_id'],
                    'quantity' => Decimal::normalize($item['quantity']),
                ])
                ->sortBy('sale_item_id')
                ->values();

            $existingItems = $existing->items
                ->map(fn (SaleReturnItem $item) => [
                    'sale_item_id' => (int) $item->sale_item_id,
                    'quantity' => Decimal::normalize($item->quantity),
                ])
                ->sortBy('sale_item_id')
                ->values();

            if ($requestedItems->count() !== $existingItems->count()) {
                throw new DomainException('The return idempotency key is already bound to another item set.');
            }

            foreach ($requestedItems as $index => $requested) {
                $recorded = $existingItems[$index];

                if (
                    $requested['sale_item_id'] !== $recorded['sale_item_id']
                    || Decimal::compare($requested['quantity'], $recorded['quantity']) !== 0
                ) {
                    throw new DomainException('The return idempotency key is already bound to another item set.');
                }
            }
        }

        if (Decimal::isPositive($existing->refund_total)) {
            $requestedMethods = collect($data['refunds'] ?? [])
                ->pluck('payment_method_id')
                ->map(fn ($id) => (int) $id)
                ->values();
            $recordedMethods = $existing->refunds
                ->pluck('payment_method_id')
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($requestedMethods->all() !== $recordedMethods->all()) {
                throw new DomainException('The return idempotency key is already bound to another refund method set.');
            }
        }
    }

    private function prepareRequestedItems(Sale $sale, array $items): Collection
    {
        if ($items === []) {
            throw new DomainException('A return requires at least one item.');
        }

        $prepared = collect();
        $seen = [];

        foreach ($items as $input) {
            $saleItemId = (int) ($input['sale_item_id'] ?? 0);

            if ($saleItemId <= 0 || isset($seen[$saleItemId])) {
                throw new DomainException('Each returned sale item must be unique.');
            }

            $seen[$saleItemId] = true;
            $saleItem = $sale->items->firstWhere('id', $saleItemId);

            if (! $saleItem) {
                throw new DomainException('The selected item does not belong to this sale.');
            }

            $quantity = Decimal::normalize($input['quantity']);

            if (! Decimal::isPositive($quantity)) {
                throw new DomainException('Return quantity must be greater than zero.');
            }

            if (Decimal::fractionalDigits($input['quantity']) > $saleItem->productUnit->unit->decimal_places) {
                throw new DomainException('Return quantity exceeds the selected unit precision.');
            }

            $prepared->push($this->prepareItem($saleItem, $quantity));
        }

        return $prepared;
    }

    private function prepareVoidItems(Sale $sale): Collection
    {
        return $sale->items
            ->map(function (SaleItem $saleItem): ?array {
                $returnedQty = $this->returnedQuantity($saleItem);
                $remainingQty = Decimal::subtract($saleItem->quantity, $returnedQty);

                return Decimal::isPositive($remainingQty)
                    ? $this->prepareItem($saleItem, $remainingQty)
                    : null;
            })
            ->filter()
            ->values();
    }

    private function prepareItem(SaleItem $saleItem, string $quantity): array
    {
        $returnedQty = $this->returnedQuantity($saleItem);
        $remainingQty = Decimal::subtract($saleItem->quantity, $returnedQty);

        if (Decimal::compare($quantity, $remainingQty) > 0) {
            throw new DomainException('Return quantity exceeds the remaining returnable quantity.');
        }

        $returnedAmount = Decimal::normalize(
            (string) $saleItem->returnItems()->sum('return_amount'),
            2,
        );
        $remainingAmount = Decimal::subtract($saleItem->line_net_total, $returnedAmount, 2);

        $returnAmount = Decimal::compare($quantity, $remainingQty) === 0
            ? $remainingAmount
            : Decimal::multiplyRounded(
                $quantity,
                Decimal::divide($saleItem->line_net_total, $saleItem->quantity, 6),
                2,
            );

        if (Decimal::compare($returnAmount, $remainingAmount) > 0) {
            $returnAmount = $remainingAmount;
        }

        return [
            'sale_item' => $saleItem,
            'quantity' => $quantity,
            'quantity_base' => Decimal::multiply($quantity, $saleItem->conversion_factor),
            'return_amount' => $returnAmount,
        ];
    }

    private function returnedQuantity(SaleItem $saleItem): string
    {
        return Decimal::normalize((string) $saleItem->returnItems()->sum('quantity'));
    }

    private function prepareRefunds(array $refunds, string $refundDue): array
    {
        if (! Decimal::isPositive($refundDue)) {
            return [];
        }

        if ($refunds === []) {
            throw new DomainException('Refund payment evidence is required for the paid portion of this reversal.');
        }

        $prepared = [];
        $total = '0.00';

        foreach ($refunds as $index => $refund) {
            $amount = ($refund['amount'] ?? null) === null && count($refunds) === 1
                ? $refundDue
                : Decimal::normalize($refund['amount'], 2);

            if (! Decimal::isPositive($amount)) {
                throw new DomainException('Refund amount must be greater than zero.');
            }

            $method = PaymentMethod::query()
                ->whereKey($refund['payment_method_id'])
                ->where('is_active', true)
                ->first();

            if (! $method) {
                throw new DomainException('The selected refund payment method is unavailable.');
            }

            $total = Decimal::add($total, $amount, 2);

            $prepared[] = [
                'method' => $method,
                'amount' => $amount,
                'reference' => $refund['reference'] ?? null,
                'notes' => $refund['notes'] ?? null,
            ];
        }

        if (Decimal::compare($total, $refundDue) !== 0) {
            throw new DomainException('Refund payment amounts must equal the paid portion being reversed.');
        }

        return $prepared;
    }
}
