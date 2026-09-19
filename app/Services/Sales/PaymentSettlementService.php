<?php

namespace App\Services\Sales;

use App\Enums\SalePaymentStatus;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Customers\CustomerLedgerService;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class PaymentSettlementService
{
    public function __construct(
        private readonly CustomerLedgerService $ledger,
        private readonly AuditLogger $audit,
    ) {
    }

    public function finalizeCheckout(
        Sale $sale,
        array $payments,
        ?Customer $customer,
        User $actor,
    ): Sale {
        return DB::transaction(function () use ($sale, $payments, $customer, $actor): Sale {
            $lockedSale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($lockedSale->settlement_finalized_at) {
                $this->assertRetryMatches($lockedSale, $payments, $customer);

                return $lockedSale->fresh(['customer', 'payments.paymentMethod']);
            }

            if (Decimal::isPositive($lockedSale->paid_amount)) {
                throw new DomainException('This sale already contains payment activity and cannot use checkout settlement.');
            }

            $lockedCustomer = null;

            if ($customer) {
                $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

                if (! $lockedCustomer->is_active) {
                    throw new DomainException('The selected customer is inactive.');
                }

                if ((int) $lockedSale->customer_id !== (int) $lockedCustomer->id) {
                    throw new DomainException('Sale customer does not match the settlement customer.');
                }
            } elseif ($lockedSale->customer_id !== null) {
                throw new DomainException('The sale customer could not be resolved.');
            }

            $prepared = $this->preparePayments($payments);
            $paidTotal = '0.00';

            foreach ($prepared as $payment) {
                $paidTotal = Decimal::add($paidTotal, $payment['applied_amount'], 2);
            }

            if (Decimal::compare($paidTotal, $lockedSale->net_total) > 0) {
                throw new DomainException('Applied payments cannot exceed the sale total.');
            }

            $balanceDue = Decimal::subtract($lockedSale->net_total, $paidTotal, 2);

            if (Decimal::isPositive($balanceDue)) {
                if (! $lockedCustomer) {
                    throw new DomainException('A registered customer is required when a sale leaves a balance due.');
                }

                if (! $actor->hasPermission('sales.credit')) {
                    throw new DomainException('The user is not allowed to create customer credit.');
                }

                $projected = Decimal::add($lockedCustomer->current_balance, $balanceDue, 2);

                if (
                    Decimal::compare($projected, $lockedCustomer->credit_limit) > 0
                    && ! $actor->hasPermission('sales.override_credit_limit')
                ) {
                    throw new DomainException('The customer credit limit would be exceeded.');
                }
            }

            if ($lockedCustomer) {
                $this->ledger->debit(
                    customer: $lockedCustomer,
                    amount: $lockedSale->net_total,
                    entryType: 'sale',
                    referenceType: 'sale',
                    referenceId: $lockedSale->id,
                    referenceNumber: $lockedSale->number,
                    actor: $actor,
                    notes: 'Sale receivable',
                );
            }

            foreach ($prepared as $index => $payment) {
                $salePayment = SalePayment::query()->firstOrCreate(
                    ['idempotency_key' => $lockedSale->idempotency_key.':checkout:'.$index],
                    [
                        'sale_id' => $lockedSale->id,
                        'customer_id' => $lockedCustomer?->id,
                        'payment_method_id' => $payment['method']->id,
                        'recorded_by_user_id' => $actor->id,
                        'applied_amount' => $payment['applied_amount'],
                        'tendered_amount' => $payment['tendered_amount'],
                        'change_amount' => $payment['change_amount'],
                        'reference' => $payment['reference'],
                        'source_type' => 'checkout',
                        'source_id' => $lockedSale->id,
                        'paid_at' => now(),
                        'notes' => $payment['notes'],
                    ],
                );

                if ($lockedCustomer) {
                    $this->ledger->credit(
                        customer: $lockedCustomer,
                        amount: $salePayment->applied_amount,
                        entryType: 'sale_payment',
                        referenceType: 'sale_payment',
                        referenceId: $salePayment->id,
                        referenceNumber: $lockedSale->number,
                        actor: $actor,
                        notes: 'Payment applied at checkout',
                    );
                }
            }

            $status = Decimal::compare($balanceDue, '0') === 0
                ? SalePaymentStatus::Paid
                : (Decimal::isPositive($paidTotal) ? SalePaymentStatus::Partial : SalePaymentStatus::Unpaid);

            $lockedSale->forceFill([
                'payment_status' => $status,
                'paid_amount' => $paidTotal,
                'balance_due' => $balanceDue,
                'settlement_finalized_at' => now(),
            ])->save();

            $this->audit->record(
                'sales.payment.settled',
                model: $lockedSale,
                newValues: [
                    'payment_status' => $status->value,
                    'paid_amount' => $paidTotal,
                    'balance_due' => $balanceDue,
                    'customer_id' => $lockedCustomer?->id,
                ],
                actor: $actor,
            );

            return $lockedSale->fresh(['customer', 'payments.paymentMethod']);
        });
    }

    private function assertRetryMatches(Sale $sale, array $payments, ?Customer $customer): void
    {
        $requestedCustomerId = $customer?->id;

        if (($sale->customer_id ? (int) $sale->customer_id : null) !== ($requestedCustomerId ? (int) $requestedCustomerId : null)) {
            throw new DomainException('The checkout idempotency key is already bound to another customer selection.');
        }

        $prepared = $this->preparePayments($payments);
        $existing = SalePayment::query()
            ->with('paymentMethod')
            ->where('sale_id', $sale->id)
            ->where('source_type', 'checkout')
            ->orderBy('id')
            ->get();

        if ($existing->count() !== count($prepared)) {
            throw new DomainException('The checkout idempotency key is already bound to another payment set.');
        }

        foreach ($prepared as $index => $payment) {
            $recorded = $existing[$index];

            if (
                (int) $recorded->payment_method_id !== (int) $payment['method']->id
                || Decimal::compare($recorded->applied_amount, $payment['applied_amount']) !== 0
                || Decimal::compare($recorded->change_amount, $payment['change_amount']) !== 0
            ) {
                throw new DomainException('The checkout idempotency key is already bound to another payment set.');
            }
        }
    }

    private function preparePayments(array $payments): array
    {
        $prepared = [];

        foreach ($payments as $payment) {
            $amount = Decimal::normalize($payment['amount'], 2);

            if (! Decimal::isPositive($amount)) {
                throw new DomainException('Payment amount must be greater than zero.');
            }

            $method = PaymentMethod::query()
                ->whereKey($payment['payment_method_id'])
                ->where('is_active', true)
                ->first();

            if (! $method) {
                throw new DomainException('The selected payment method is unavailable.');
            }

            $tendered = null;
            $change = '0.00';

            if ($method->is_cash) {
                $tendered = Decimal::normalize($payment['tendered_amount'] ?? $amount, 2);

                if (Decimal::compare($tendered, $amount) < 0) {
                    throw new DomainException('Cash tendered cannot be less than the applied cash amount.');
                }

                $change = Decimal::subtract($tendered, $amount, 2);
            } elseif (isset($payment['tendered_amount']) && $payment['tendered_amount'] !== null) {
                $tendered = Decimal::normalize($payment['tendered_amount'], 2);

                if (Decimal::compare($tendered, $amount) !== 0) {
                    throw new DomainException('Non-cash tendered amount must equal the applied amount.');
                }
            }

            $prepared[] = [
                'method' => $method,
                'applied_amount' => $amount,
                'tendered_amount' => $tendered,
                'change_amount' => $change,
                'reference' => $payment['reference'] ?? null,
                'notes' => $payment['notes'] ?? null,
            ];
        }

        return $prepared;
    }
}
