<?php

namespace App\Services\Cash;

use App\Enums\ShiftStatus;
use App\Models\CashierShift;
use App\Models\CashMovement;
use App\Models\User;
use App\Services\Closing\BusinessDayService;
use App\Support\Decimal;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class CashMovementService
{
    public function __construct(
        private readonly BusinessDayService $days,
    ) {
    }
    public function recordSource(
        User $actor,
        string $amount,
        string $direction,
        string $movementType,
        ?string $sourceType,
        ?int $sourceId,
        ?string $referenceNumber = null,
        ?string $reason = null,
        ?CarbonInterface $occurredAt = null,
        ?CashierShift $shift = null,
        ?string $idempotencyKey = null,
    ): CashMovement {
        return DB::transaction(function () use (
            $actor,
            $amount,
            $direction,
            $movementType,
            $sourceType,
            $sourceId,
            $referenceNumber,
            $reason,
            $occurredAt,
            $shift,
            $idempotencyKey,
        ): CashMovement {
            $amount = Decimal::normalize($amount, 2);

            if (! Decimal::isPositive($amount)) {
                throw new DomainException('Cash movement amount must be greater than zero.');
            }

            if (! in_array($direction, ['inflow', 'outflow'], true)) {
                throw new DomainException('Cash movement direction is invalid.');
            }

            $key = $idempotencyKey ?? $sourceType.':'.$sourceId.':'.$movementType;

            if ($existing = CashMovement::query()->where('idempotency_key', $key)->first()) {
                $this->assertExistingMatches(
                    $existing,
                    $amount,
                    $direction,
                    $movementType,
                    $sourceType,
                    $sourceId,
                );

                return $existing;
            }

            $candidateShift = $shift ?: CashierShift::query()
                ->where('user_id', $actor->id)
                ->where('status', ShiftStatus::Open->value)
                ->latest('opened_at')
                ->first();

            if (! $candidateShift) {
                throw new DomainException('Open a cashier shift before recording a cash transaction.');
            }

            $businessDate = $candidateShift->business_date?->format('Y-m-d')
                ?? $candidateShift->opened_at->format('Y-m-d');

            $this->days->lockOpen($businessDate);

            $lockedShift = $this->resolveOpenShift($actor, $candidateShift);
            $movementTime = $occurredAt ?? now();

            if ($movementTime->lt($lockedShift->opened_at)) {
                throw new DomainException('Cash transaction time cannot be before the cashier shift opened.');
            }

            $this->ensureOpeningMovement($lockedShift, $actor);

            $movement = CashMovement::create([
                'idempotency_key' => $key,
                'cashier_shift_id' => $lockedShift->id,
                'terminal_id' => $lockedShift->terminal_id,
                'actor_user_id' => $actor->id,
                'movement_type' => $movementType,
                'direction' => $direction,
                'amount' => $amount,
                'expected_cash_after' => '0.00',
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'reference_number' => $referenceNumber,
                'reason' => $reason,
                'occurred_at' => $movementTime,
                'created_at' => now(),
            ]);

            $expected = $this->recalculateExpectedCash($lockedShift);

            DB::table('cash_movements')
                ->where('id', $movement->id)
                ->update(['expected_cash_after' => $expected]);

            return $movement->fresh();
        });
    }

    public function recordManual(
        User $actor,
        CashierShift $shift,
        string $idempotencyKey,
        string $movementType,
        string $amount,
        string $reason,
    ): CashMovement {
        if (! $actor->hasPermission('cash.manage')) {
            throw new DomainException('The user is not allowed to record manual cash movements.');
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('A reason is required for manual cash movements.');
        }

        $direction = match ($movementType) {
            'cash_deposit' => 'inflow',
            'cash_withdrawal', 'drawer_to_safe' => 'outflow',
            default => throw new DomainException('Unsupported manual cash movement type.'),
        };

        return $this->recordSource(
            actor: $actor,
            amount: $amount,
            direction: $direction,
            movementType: $movementType,
            sourceType: null,
            sourceId: null,
            reason: $reason,
            shift: $shift,
            idempotencyKey: $idempotencyKey,
        );
    }

    public function ensureOpeningMovement(CashierShift $shift, ?User $actor = null): CashMovement
    {
        return DB::transaction(function () use ($shift, $actor): CashMovement {
            $lockedShift = CashierShift::query()->lockForUpdate()->findOrFail($shift->id);
            $key = 'shift:'.$lockedShift->id.':opening';

            if ($existing = CashMovement::query()->where('idempotency_key', $key)->first()) {
                return $existing;
            }

            $movement = CashMovement::create([
                'idempotency_key' => $key,
                'cashier_shift_id' => $lockedShift->id,
                'terminal_id' => $lockedShift->terminal_id,
                'actor_user_id' => $actor?->id ?? $lockedShift->user_id,
                'movement_type' => 'opening_float',
                'direction' => 'inflow',
                'amount' => Decimal::normalize($lockedShift->opening_cash, 2),
                'expected_cash_after' => Decimal::normalize($lockedShift->opening_cash, 2),
                'source_type' => 'cashier_shift',
                'source_id' => $lockedShift->id,
                'reference_number' => null,
                'reason' => 'Shift opening float.',
                'occurred_at' => $lockedShift->opened_at,
                'created_at' => now(),
            ]);

            $lockedShift->forceFill([
                'expected_cash' => Decimal::normalize($lockedShift->opening_cash, 2),
            ])->save();

            return $movement;
        });
    }

    public function recalculateExpectedCash(CashierShift $shift): string
    {
        return DB::transaction(function () use ($shift): string {
            $lockedShift = CashierShift::query()->lockForUpdate()->findOrFail($shift->id);

            $inflow = Decimal::normalize(
                (string) CashMovement::query()
                    ->where('cashier_shift_id', $lockedShift->id)
                    ->where('direction', 'inflow')
                    ->sum('amount'),
                2,
            );
            $outflow = Decimal::normalize(
                (string) CashMovement::query()
                    ->where('cashier_shift_id', $lockedShift->id)
                    ->where('direction', 'outflow')
                    ->sum('amount'),
                2,
            );

            $expected = Decimal::subtract($inflow, $outflow, 2);

            $lockedShift->forceFill(['expected_cash' => $expected])->save();

            return $expected;
        });
    }

    private function resolveOpenShift(User $actor, ?CashierShift $shift): CashierShift
    {
        if (! $shift) {
            throw new DomainException('Open a cashier shift before recording a cash transaction.');
        }

        $locked = CashierShift::query()
            ->lockForUpdate()
            ->findOrFail($shift->id);

        if ($locked->status !== ShiftStatus::Open) {
            throw new DomainException('Cash movement requires an open cashier shift.');
        }

        if ((int) $locked->user_id !== (int) $actor->id && ! $actor->hasPermission('cash.manage')) {
            throw new DomainException('The selected cash drawer belongs to another user.');
        }

        return $locked;
    }

    private function assertExistingMatches(
        CashMovement $existing,
        string $amount,
        string $direction,
        string $movementType,
        ?string $sourceType,
        ?int $sourceId,
    ): void {
        if (
            Decimal::compare($existing->amount, $amount) !== 0
            || $existing->direction !== $direction
            || $existing->movement_type !== $movementType
            || ($existing->source_type ?? null) !== $sourceType
            || ($existing->source_id !== null ? (int) $existing->source_id : null) !== $sourceId
        ) {
            throw new DomainException('The cash movement idempotency key is already bound to another payload.');
        }
    }
}
