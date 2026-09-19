<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\User;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class CustomerLedgerService
{
    public function debit(
        Customer $customer,
        string $amount,
        string $entryType,
        string $referenceType,
        int $referenceId,
        ?string $referenceNumber,
        ?User $actor,
        ?string $notes = null,
    ): CustomerLedgerEntry {
        return $this->record(
            customer: $customer,
            debit: $amount,
            credit: '0.00',
            entryType: $entryType,
            referenceType: $referenceType,
            referenceId: $referenceId,
            referenceNumber: $referenceNumber,
            actor: $actor,
            notes: $notes,
        );
    }

    public function credit(
        Customer $customer,
        string $amount,
        string $entryType,
        string $referenceType,
        int $referenceId,
        ?string $referenceNumber,
        ?User $actor,
        ?string $notes = null,
    ): CustomerLedgerEntry {
        return $this->record(
            customer: $customer,
            debit: '0.00',
            credit: $amount,
            entryType: $entryType,
            referenceType: $referenceType,
            referenceId: $referenceId,
            referenceNumber: $referenceNumber,
            actor: $actor,
            notes: $notes,
        );
    }

    private function record(
        Customer $customer,
        string $debit,
        string $credit,
        string $entryType,
        string $referenceType,
        int $referenceId,
        ?string $referenceNumber,
        ?User $actor,
        ?string $notes,
    ): CustomerLedgerEntry {
        return DB::transaction(function () use (
            $customer,
            $debit,
            $credit,
            $entryType,
            $referenceType,
            $referenceId,
            $referenceNumber,
            $actor,
            $notes,
        ): CustomerLedgerEntry {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            $existing = CustomerLedgerEntry::query()
                ->where('customer_id', $locked->id)
                ->where('entry_type', $entryType)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->first();

            if ($existing) {
                return $existing;
            }

            $debit = Decimal::normalize($debit, 2);
            $credit = Decimal::normalize($credit, 2);

            if (Decimal::isNegative($debit) || Decimal::isNegative($credit)) {
                throw new DomainException('Customer ledger amounts cannot be negative.');
            }

            if (Decimal::isPositive($debit) === Decimal::isPositive($credit)) {
                throw new DomainException('A customer ledger entry must contain exactly one debit or credit amount.');
            }

            $balance = Decimal::subtract(
                Decimal::add($locked->current_balance, $debit, 2),
                $credit,
                2,
            );

            if (Decimal::isNegative($balance)) {
                throw new DomainException('Customer balance cannot become negative.');
            }

            $entry = CustomerLedgerEntry::create([
                'customer_id' => $locked->id,
                'actor_user_id' => $actor?->id,
                'entry_type' => $entryType,
                'debit' => $debit,
                'credit' => $credit,
                'balance_after' => $balance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_number' => $referenceNumber,
                'occurred_at' => now(),
                'notes' => $notes,
                'created_at' => now(),
            ]);

            $locked->forceFill(['current_balance' => $balance])->save();

            return $entry;
        });
    }
}
