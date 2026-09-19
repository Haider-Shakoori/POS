<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Support\Decimal;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class SupplierLedgerService
{
    public function credit(
        Supplier $supplier,
        string $amount,
        string $entryType,
        string $referenceType,
        int $referenceId,
        ?string $referenceNumber,
        ?User $actor,
        ?string $notes = null,
        ?CarbonInterface $occurredAt = null,
    ): SupplierLedgerEntry {
        return $this->record(
            supplier: $supplier,
            debit: '0.00',
            credit: $amount,
            entryType: $entryType,
            referenceType: $referenceType,
            referenceId: $referenceId,
            referenceNumber: $referenceNumber,
            actor: $actor,
            notes: $notes,
            occurredAt: $occurredAt,
        );
    }

    public function debit(
        Supplier $supplier,
        string $amount,
        string $entryType,
        string $referenceType,
        int $referenceId,
        ?string $referenceNumber,
        ?User $actor,
        ?string $notes = null,
        ?CarbonInterface $occurredAt = null,
    ): SupplierLedgerEntry {
        return $this->record(
            supplier: $supplier,
            debit: $amount,
            credit: '0.00',
            entryType: $entryType,
            referenceType: $referenceType,
            referenceId: $referenceId,
            referenceNumber: $referenceNumber,
            actor: $actor,
            notes: $notes,
            occurredAt: $occurredAt,
        );
    }

    private function record(
        Supplier $supplier,
        string $debit,
        string $credit,
        string $entryType,
        string $referenceType,
        int $referenceId,
        ?string $referenceNumber,
        ?User $actor,
        ?string $notes,
        ?CarbonInterface $occurredAt,
    ): SupplierLedgerEntry {
        return DB::transaction(function () use (
            $supplier,
            $debit,
            $credit,
            $entryType,
            $referenceType,
            $referenceId,
            $referenceNumber,
            $actor,
            $notes,
            $occurredAt,
        ): SupplierLedgerEntry {
            $locked = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);

            if ($existing = SupplierLedgerEntry::query()
                ->where('supplier_id', $locked->id)
                ->where('entry_type', $entryType)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->first()) {
                return $existing;
            }

            $debit = Decimal::normalize($debit, 2);
            $credit = Decimal::normalize($credit, 2);

            if (Decimal::isNegative($debit) || Decimal::isNegative($credit)) {
                throw new DomainException('Supplier ledger amounts cannot be negative.');
            }

            if (Decimal::isPositive($debit) === Decimal::isPositive($credit)) {
                throw new DomainException('A supplier ledger entry must contain exactly one debit or credit amount.');
            }

            // Positive balance = payable to supplier. Negative balance = supplier credit owed to shop.
            $balance = Decimal::subtract(
                Decimal::add($locked->current_balance, $credit, 2),
                $debit,
                2,
            );

            $entry = SupplierLedgerEntry::create([
                'supplier_id' => $locked->id,
                'actor_user_id' => $actor?->id,
                'entry_type' => $entryType,
                'debit' => $debit,
                'credit' => $credit,
                'balance_after' => $balance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_number' => $referenceNumber,
                'occurred_at' => $occurredAt ?? now(),
                'notes' => $notes,
                'created_at' => now(),
            ]);

            $locked->forceFill(['current_balance' => $balance])->save();

            return $entry;
        });
    }
}
