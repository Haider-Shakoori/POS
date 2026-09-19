<?php

namespace App\Services\Customers;

use App\Enums\SalePaymentStatus;
use App\Models\Customer;
use App\Models\CustomerCollection;
use App\Models\CustomerCollectionAllocation;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentNumberService;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class CustomerCollectionService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly CustomerLedgerService $ledger,
        private readonly AuditLogger $audit,
    ) {
    }

    public function collect(Customer $customer, array $data, User $actor): CustomerCollection
    {
        return DB::transaction(function () use ($customer, $data, $actor): CustomerCollection {
            if (! $actor->hasPermission('customers.collect')) {
                throw new DomainException('The user is not allowed to record customer collections.');
            }

            if ($existing = CustomerCollection::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first()) {
                if ((int) $existing->customer_id !== (int) $customer->id) {
                    throw new DomainException('The collection idempotency key belongs to another customer.');
                }

                if (
                    (int) $existing->payment_method_id !== (int) $data['payment_method_id']
                    || Decimal::compare($existing->amount, Decimal::normalize($data['amount'], 2)) !== 0
                ) {
                    throw new DomainException('The collection idempotency key is already bound to another collection payload.');
                }

                return $existing->load(['paymentMethod', 'allocations.sale']);
            }

            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            if (! $lockedCustomer->is_active) {
                throw new DomainException('Collections cannot be posted to an inactive customer.');
            }

            $amount = Decimal::normalize($data['amount'], 2);

            if (! Decimal::isPositive($amount)) {
                throw new DomainException('Collection amount must be greater than zero.');
            }

            if (Decimal::compare($amount, $lockedCustomer->current_balance) > 0) {
                throw new DomainException('Collection amount cannot exceed the customer balance.');
            }

            $method = PaymentMethod::query()
                ->whereKey($data['payment_method_id'])
                ->where('is_active', true)
                ->first();

            if (! $method) {
                throw new DomainException('The selected payment method is unavailable.');
            }

            $tendered = null;
            $change = '0.00';

            if ($method->is_cash) {
                $tendered = Decimal::normalize($data['tendered_amount'] ?? $amount, 2);

                if (Decimal::compare($tendered, $amount) < 0) {
                    throw new DomainException('Cash tendered cannot be less than the collection amount.');
                }

                $change = Decimal::subtract($tendered, $amount, 2);
            } elseif (isset($data['tendered_amount']) && $data['tendered_amount'] !== null) {
                $tendered = Decimal::normalize($data['tendered_amount'], 2);

                if (Decimal::compare($tendered, $amount) !== 0) {
                    throw new DomainException('Non-cash tendered amount must equal the collection amount.');
                }
            }

            $collection = CustomerCollection::create([
                'number' => $this->numbers->next('customer_collection', 'COL'),
                'idempotency_key' => $data['idempotency_key'],
                'customer_id' => $lockedCustomer->id,
                'payment_method_id' => $method->id,
                'recorded_by_user_id' => $actor->id,
                'amount' => $amount,
                'tendered_amount' => $tendered,
                'change_amount' => $change,
                'reference' => $data['reference'] ?? null,
                'collected_at' => $data['collected_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->ledger->credit(
                customer: $lockedCustomer,
                amount: $amount,
                entryType: 'collection',
                referenceType: 'collection',
                referenceId: $collection->id,
                referenceNumber: $collection->number,
                actor: $actor,
                notes: 'Customer collection',
            );

            $remaining = $amount;

            $sales = Sale::query()
                ->where('customer_id', $lockedCustomer->id)
                ->where('balance_due', '>', 0)
                ->orderBy('sold_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($sales as $sale) {
                if (! Decimal::isPositive($remaining)) {
                    break;
                }

                $applied = Decimal::compare($sale->balance_due, $remaining) <= 0
                    ? $sale->balance_due
                    : $remaining;

                CustomerCollectionAllocation::create([
                    'customer_collection_id' => $collection->id,
                    'sale_id' => $sale->id,
                    'amount' => $applied,
                ]);

                SalePayment::create([
                    'idempotency_key' => 'collection:'.$collection->id.':sale:'.$sale->id,
                    'sale_id' => $sale->id,
                    'customer_id' => $lockedCustomer->id,
                    'payment_method_id' => $method->id,
                    'recorded_by_user_id' => $actor->id,
                    'applied_amount' => $applied,
                    'tendered_amount' => null,
                    'change_amount' => '0.00',
                    'reference' => $collection->reference,
                    'source_type' => 'collection',
                    'source_id' => $collection->id,
                    'paid_at' => $collection->collected_at,
                    'notes' => 'Allocated from '.$collection->number,
                ]);

                $paid = Decimal::add($sale->paid_amount, $applied, 2);
                $balance = Decimal::subtract($sale->balance_due, $applied, 2);
                $status = Decimal::compare($balance, '0') === 0
                    ? SalePaymentStatus::Paid
                    : SalePaymentStatus::Partial;

                $sale->forceFill([
                    'payment_status' => $status,
                    'paid_amount' => $paid,
                    'balance_due' => $balance,
                ])->save();

                $remaining = Decimal::subtract($remaining, $applied, 2);
            }

            $this->audit->record(
                'customers.collection.recorded',
                model: $collection,
                newValues: [
                    'customer_id' => $lockedCustomer->id,
                    'amount' => $amount,
                    'payment_method_id' => $method->id,
                ],
                actor: $actor,
            );

            return $collection->fresh(['customer', 'paymentMethod', 'allocations.sale']);
        });
    }
}
