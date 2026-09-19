<?php

namespace App\Services\Cash;

use App\Models\ExpenseCategory;
use App\Models\OperatingEntry;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentNumberService;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class OperatingEntryService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly CashMovementService $cash,
        private readonly AuditLogger $audit,
    ) {
    }

    public function record(array $data, User $actor): OperatingEntry
    {
        if (! $actor->hasPermission('expenses.create')) {
            throw new DomainException('The user is not allowed to record operating entries.');
        }

        return DB::transaction(function () use ($data, $actor): OperatingEntry {
            if ($existing = OperatingEntry::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first()) {
                $this->assertRetryMatches($existing, $data);

                return $existing->load(['category', 'paymentMethod']);
            }

            $category = ExpenseCategory::query()
                ->whereKey($data['expense_category_id'])
                ->where('is_active', true)
                ->first();

            if (! $category) {
                throw new DomainException('The selected operating category is unavailable.');
            }

            $entryType = (string) $data['entry_type'];

            if (! in_array($entryType, ['expense', 'income'], true) || $category->entry_type !== $entryType) {
                throw new DomainException('The category does not match the operating entry type.');
            }

            $method = PaymentMethod::query()
                ->whereKey($data['payment_method_id'])
                ->where('is_active', true)
                ->first();

            if (! $method) {
                throw new DomainException('The selected payment method is unavailable.');
            }

            $amount = Decimal::normalize($data['amount'], 2);

            if (! Decimal::isPositive($amount)) {
                throw new DomainException('Operating entry amount must be greater than zero.');
            }

            $occurredAt = ! empty($data['occurred_at'])
                ? CarbonImmutable::parse($data['occurred_at'])
                : now();

            $entry = OperatingEntry::create([
                'number' => $this->numbers->next('operating_entry', $entryType === 'expense' ? 'EXP' : 'INC'),
                'idempotency_key' => $data['idempotency_key'],
                'expense_category_id' => $category->id,
                'payment_method_id' => $method->id,
                'recorded_by_user_id' => $actor->id,
                'entry_type' => $entryType,
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'occurred_at' => $occurredAt,
            ]);

            if ($method->is_cash) {
                $this->cash->recordSource(
                    actor: $actor,
                    amount: $amount,
                    direction: $entryType === 'expense' ? 'outflow' : 'inflow',
                    movementType: $entryType === 'expense' ? 'expense' : 'other_income',
                    sourceType: 'operating_entry',
                    sourceId: $entry->id,
                    referenceNumber: $entry->number,
                    reason: $entry->description,
                    occurredAt: $entry->occurred_at,
                );
            }

            $this->audit->record(
                'cash.operating_entry.recorded',
                model: $entry,
                newValues: [
                    'entry_type' => $entryType,
                    'amount' => $amount,
                    'payment_method_id' => $method->id,
                ],
                actor: $actor,
            );

            return $entry->fresh(['category', 'paymentMethod']);
        });
    }

    private function assertRetryMatches(OperatingEntry $existing, array $data): void
    {
        if (
            $existing->entry_type !== (string) $data['entry_type']
            || (int) $existing->expense_category_id !== (int) $data['expense_category_id']
            || (int) $existing->payment_method_id !== (int) $data['payment_method_id']
            || Decimal::compare($existing->amount, Decimal::normalize($data['amount'], 2)) !== 0
        ) {
            throw new DomainException('The operating entry idempotency key is already bound to another payload.');
        }
    }
}
