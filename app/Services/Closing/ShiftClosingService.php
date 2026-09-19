<?php

namespace App\Services\Closing;

use App\Enums\ShiftStatus;
use App\Models\CashierShift;
use App\Models\CashierShiftClosure;
use App\Models\ShopSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Cash\CashMovementService;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class ShiftClosingService
{
    public function __construct(
        private readonly BusinessDayService $days,
        private readonly CashMovementService $cash,
        private readonly AuditLogger $audit,
    ) {
    }

    public function close(CashierShift $shift, array $data, User $actor): CashierShiftClosure
    {
        if (! $actor->hasPermission('shifts.close')) {
            throw new DomainException('The user is not allowed to close cashier shifts.');
        }

        return DB::transaction(function () use ($shift, $data, $actor): CashierShiftClosure {
            if ($existing = CashierShiftClosure::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first()) {
                if ((int) $existing->cashier_shift_id !== (int) $shift->id) {
                    throw new DomainException('The shift-close idempotency key belongs to another shift.');
                }

                $this->assertRetryMatches($existing, $data);

                return $existing->load(['shift.terminal', 'closedBy']);
            }

            $candidate = CashierShift::query()->findOrFail($shift->id);
            $businessDate = $candidate->business_date?->format('Y-m-d')
                ?? $candidate->opened_at->format('Y-m-d');

            $this->days->lockOpen($businessDate);

            $locked = CashierShift::query()->lockForUpdate()->findOrFail($shift->id);

            if (
                (int) $locked->user_id !== (int) $actor->id
                && ! $actor->hasPermission('cash.manage')
            ) {
                throw new DomainException('The user is not allowed to close another cashier’s shift.');
            }

            if ($locked->status !== ShiftStatus::Open) {
                throw new DomainException('Only an open cashier shift can be closed.');
            }

            $actual = Decimal::normalize($data['actual_cash'], 2);

            if (Decimal::isNegative($actual)) {
                throw new DomainException('Actual cash cannot be negative.');
            }

            $expected = $this->cash->recalculateExpectedCash($locked);
            $variance = Decimal::subtract($actual, $expected, 2);
            $tolerance = Decimal::normalize(
                (string) (ShopSetting::query()->value('cash_variance_tolerance') ?? '0'),
                2,
            );
            $absoluteVariance = Decimal::isNegative($variance)
                ? Decimal::subtract('0.00', $variance, 2)
                : $variance;
            $withinTolerance = Decimal::compare($absoluteVariance, $tolerance) <= 0;
            $varianceReason = trim((string) ($data['variance_reason'] ?? ''));

            if (! $withinTolerance && $varianceReason === '') {
                throw new DomainException('A variance reason is required when the cash difference exceeds the configured tolerance.');
            }

            $version = (int) CashierShiftClosure::query()
                ->where('cashier_shift_id', $locked->id)
                ->max('version') + 1;

            $closure = CashierShiftClosure::create([
                'idempotency_key' => $data['idempotency_key'],
                'cashier_shift_id' => $locked->id,
                'version' => $version,
                'closed_by_user_id' => $actor->id,
                'expected_cash' => $expected,
                'actual_cash' => $actual,
                'variance' => $variance,
                'tolerance' => $tolerance,
                'within_tolerance' => $withinTolerance,
                'variance_reason' => $varianceReason !== '' ? $varianceReason : null,
                'closing_notes' => $data['closing_notes'] ?? null,
                'closed_at' => now(),
                'created_at' => now(),
            ]);

            $locked->forceFill([
                'business_date' => $businessDate,
                'closed_at' => $closure->closed_at,
                'closed_by_user_id' => $actor->id,
                'expected_cash' => $expected,
                'actual_cash' => $actual,
                'variance' => $variance,
                'variance_within_tolerance' => $withinTolerance,
                'variance_reason' => $closure->variance_reason,
                'closing_notes' => $closure->closing_notes,
                'status' => ShiftStatus::Closed,
            ])->save();

            $this->audit->record(
                'closing.shift.closed',
                model: $locked,
                newValues: [
                    'closure_id' => $closure->id,
                    'version' => $version,
                    'expected_cash' => $expected,
                    'actual_cash' => $actual,
                    'variance' => $variance,
                    'within_tolerance' => $withinTolerance,
                ],
                actor: $actor,
            );

            return $closure->fresh(['shift.terminal', 'closedBy']);
        });
    }

    public function reopen(CashierShift $shift, string $reason, User $actor): CashierShift
    {
        if (! $actor->hasPermission('shifts.reopen')) {
            throw new DomainException('The user is not allowed to reopen cashier shifts.');
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('A reason is required to reopen a cashier shift.');
        }

        return DB::transaction(function () use ($shift, $reason, $actor): CashierShift {
            $candidate = CashierShift::query()->findOrFail($shift->id);
            $businessDate = $candidate->business_date?->format('Y-m-d')
                ?? $candidate->opened_at->format('Y-m-d');

            $this->days->lockOpen($businessDate);

            $locked = CashierShift::query()->lockForUpdate()->findOrFail($shift->id);

            if ($locked->status === ShiftStatus::Open) {
                return $locked;
            }

            $conflictingUserShift = CashierShift::query()
                ->where('user_id', $locked->user_id)
                ->where('status', ShiftStatus::Open->value)
                ->whereKeyNot($locked->id)
                ->lockForUpdate()
                ->exists();

            $conflictingTerminalShift = CashierShift::query()
                ->where('terminal_id', $locked->terminal_id)
                ->where('status', ShiftStatus::Open->value)
                ->whereKeyNot($locked->id)
                ->lockForUpdate()
                ->exists();

            if ($conflictingUserShift || $conflictingTerminalShift) {
                throw new DomainException('The shift cannot be reopened because the cashier or terminal already has another open shift.');
            }

            $old = [
                'closed_at' => $locked->closed_at?->toDateTimeString(),
                'actual_cash' => $locked->actual_cash,
                'variance' => $locked->variance,
            ];

            $locked->forceFill([
                'closed_at' => null,
                'closed_by_user_id' => null,
                'actual_cash' => null,
                'variance' => null,
                'variance_within_tolerance' => null,
                'variance_reason' => null,
                'closing_notes' => null,
                'reopened_at' => now(),
                'reopened_by_user_id' => $actor->id,
                'status' => ShiftStatus::Open,
            ])->save();

            $this->audit->record(
                'closing.shift.reopened',
                model: $locked,
                oldValues: $old,
                newValues: [
                    'status' => ShiftStatus::Open->value,
                    'reason' => $reason,
                ],
                actor: $actor,
            );

            return $locked->fresh(['terminal', 'user']);
        });
    }

    private function assertRetryMatches(CashierShiftClosure $existing, array $data): void
    {
        if (
            Decimal::compare($existing->actual_cash, Decimal::normalize($data['actual_cash'], 2)) !== 0
            || trim((string) ($existing->variance_reason ?? '')) !== trim((string) ($data['variance_reason'] ?? ''))
            || trim((string) ($existing->closing_notes ?? '')) !== trim((string) ($data['closing_notes'] ?? ''))
        ) {
            throw new DomainException('The shift-close idempotency key is already bound to another closing payload.');
        }
    }
}
