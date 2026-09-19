<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function __construct(
        private readonly CustomerLedgerService $ledger,
        private readonly AuditLogger $audit,
    ) {
    }

    public function create(array $data, User $actor): Customer
    {
        return DB::transaction(function () use ($data, $actor): Customer {
            if (! $actor->hasPermission('customers.manage') && ! $actor->hasPermission('customers.quick_create')) {
                throw new DomainException('The user is not allowed to create customers.');
            }

            $creditLimit = Decimal::normalize($data['credit_limit'] ?? '0', 2);
            $openingBalance = Decimal::normalize($data['opening_balance'] ?? '0', 2);

            if (Decimal::isNegative($creditLimit) || Decimal::isNegative($openingBalance)) {
                throw new DomainException('Customer credit amounts cannot be negative.');
            }

            $customer = Customer::create([
                'name' => trim($data['name']),
                'phone' => $data['phone'] ?? null,
                'alternate_phone' => $data['alternate_phone'] ?? null,
                'address' => $data['address'] ?? null,
                'credit_limit' => $creditLimit,
                'opening_balance' => $openingBalance,
                'current_balance' => '0.00',
                'is_active' => true,
            ]);

            if (Decimal::isPositive($openingBalance)) {
                $this->ledger->debit(
                    customer: $customer,
                    amount: $openingBalance,
                    entryType: 'opening_balance',
                    referenceType: 'customer',
                    referenceId: $customer->id,
                    referenceNumber: 'OPEN-'.$customer->id,
                    actor: $actor,
                    notes: 'Customer opening receivable',
                );
            }

            $this->audit->record(
                'customers.customer.created',
                model: $customer,
                newValues: [
                    'name' => $customer->name,
                    'credit_limit' => $customer->credit_limit,
                    'opening_balance' => $customer->opening_balance,
                ],
                actor: $actor,
            );

            return $customer->fresh();
        });
    }
}
