<?php

namespace App\Services\Suppliers;

use App\Enums\PurchasePaymentMethod;
use App\Models\GoodsReceipt;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Cash\CashMovementService;
use App\Services\Closing\BusinessDayService;
use App\Services\Documents\DocumentNumberService;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class SupplierPaymentService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly SupplierLedgerService $ledger,
        private readonly CashMovementService $cash,
        private readonly BusinessDayService $days,
        private readonly AuditLogger $audit,
    ) {
    }

    public function record(Supplier $supplier, array $data, User $actor): SupplierPayment
    {
        if (! $actor->hasPermission('suppliers.pay')) {
            throw new DomainException('The user is not allowed to record supplier payments.');
        }

        return DB::transaction(function () use ($supplier, $data, $actor): SupplierPayment {
            if ($existing = SupplierPayment::query()
                ->with(['allocations.goodsReceipt'])
                ->where('idempotency_key', $data['idempotency_key'])
                ->first()) {
                if ((int) $existing->supplier_id !== (int) $supplier->id) {
                    throw new DomainException('The supplier payment idempotency key belongs to another supplier.');
                }

                $this->assertRetryMatches($existing, $data);

                return $existing;
            }

            $lockedSupplier = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);

            if (! $lockedSupplier->is_active) {
                throw new DomainException('Payments cannot be recorded for an inactive supplier.');
            }

            $amount = Decimal::normalize($data['amount'], 2);

            if (! Decimal::isPositive($amount)) {
                throw new DomainException('Supplier payment amount must be greater than zero.');
            }

            if (! Decimal::isPositive($lockedSupplier->current_balance)) {
                throw new DomainException('This supplier has no positive payable balance.');
            }

            if (Decimal::compare($amount, $lockedSupplier->current_balance) > 0) {
                throw new DomainException('Supplier payment cannot exceed the current payable balance.');
            }

            $method = PurchasePaymentMethod::tryFrom((string) $data['method']);

            if (! $method) {
                throw new DomainException('A valid supplier payment method is required.');
            }

            $paidAt = ! empty($data['paid_at'])
                ? CarbonImmutable::parse($data['paid_at'])
                : now();

            $this->days->lockOpen($paidAt);

            $payment = SupplierPayment::create([
                'number' => $this->numbers->next('supplier_payment', 'SPY'),
                'idempotency_key' => $data['idempotency_key'],
                'supplier_id' => $lockedSupplier->id,
                'recorded_by_user_id' => $actor->id,
                'amount' => $amount,
                'method' => $method,
                'reference' => $data['reference'] ?? null,
                'paid_at' => $paidAt,
                'notes' => $data['notes'] ?? null,
            ]);

            $remaining = $amount;

            $receipts = GoodsReceipt::query()
                ->where('supplier_id', $lockedSupplier->id)
                ->where('balance_due', '>', 0)
                ->orderBy('received_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($receipts as $receipt) {
                if (! Decimal::isPositive($remaining)) {
                    break;
                }

                $take = Decimal::compare($receipt->balance_due, $remaining) <= 0
                    ? $receipt->balance_due
                    : $remaining;

                if (! Decimal::isPositive($take)) {
                    continue;
                }

                $payment->allocations()->create([
                    'goods_receipt_id' => $receipt->id,
                    'amount' => $take,
                ]);

                $receipt->forceFill([
                    'paid_amount' => Decimal::add($receipt->paid_amount, $take, 2),
                    'balance_due' => Decimal::subtract($receipt->balance_due, $take, 2),
                ])->save();

                $remaining = Decimal::subtract($remaining, $take, 2);
            }

            if ($method === PurchasePaymentMethod::Cash) {
                $this->cash->recordSource(
                    actor: $actor,
                    amount: $amount,
                    direction: 'outflow',
                    movementType: 'supplier_payment',
                    sourceType: 'supplier_payment',
                    sourceId: $payment->id,
                    referenceNumber: $payment->number,
                    reason: 'Cash supplier payment',
                    occurredAt: $payment->paid_at,
                );
            }

            $this->ledger->debit(
                supplier: $lockedSupplier,
                amount: $amount,
                entryType: 'supplier_payment',
                referenceType: 'supplier_payment',
                referenceId: $payment->id,
                referenceNumber: $payment->number,
                actor: $actor,
                notes: $payment->notes,
                occurredAt: $payment->paid_at,
            );

            $this->audit->record(
                'purchasing.supplier.payment_recorded',
                model: $payment,
                newValues: [
                    'supplier_id' => $payment->supplier_id,
                    'amount' => $payment->amount,
                    'method' => $payment->method->value,
                    'allocated_amount' => Decimal::subtract($amount, $remaining, 2),
                    'unallocated_opening_balance_amount' => $remaining,
                ],
                actor: $actor,
            );

            return $payment->fresh(['supplier', 'allocations.goodsReceipt']);
        }, 3);
    }

    private function assertRetryMatches(SupplierPayment $existing, array $data): void
    {
        $amount = Decimal::normalize($data['amount'], 2);
        $method = (string) $data['method'];

        if (
            Decimal::compare($existing->amount, $amount) !== 0
            || $existing->method->value !== $method
            || ($existing->reference ?? null) !== ($data['reference'] ?? null)
        ) {
            throw new DomainException('The supplier payment idempotency key is already bound to another payload.');
        }
    }
}
