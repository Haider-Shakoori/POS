<?php

namespace App\Services\Cash;

use App\Enums\ShiftStatus;
use App\Models\CashierShift;
use App\Models\Terminal;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class ShiftOpeningService
{
    public function __construct(
        private readonly CashMovementService $cash,
        private readonly AuditLogger $audit,
    ) {
    }

    public function open(array $data, User $actor): CashierShift
    {
        if (! $actor->hasPermission('shifts.open')) {
            throw new DomainException('The user is not allowed to open cashier shifts.');
        }

        return DB::transaction(function () use ($data, $actor): CashierShift {
            if ($existing = CashierShift::query()
                ->where('open_idempotency_key', $data['idempotency_key'])
                ->first()) {
                if ((int) $existing->user_id !== (int) $actor->id) {
                    throw new DomainException('The shift opening idempotency key belongs to another user.');
                }

                $requestedOpeningCash = Decimal::normalize($data['opening_cash'] ?? '0', 2);

                if (
                    (int) $existing->terminal_id !== (int) $data['terminal_id']
                    || Decimal::compare($existing->opening_cash, $requestedOpeningCash) !== 0
                ) {
                    throw new DomainException('The shift opening idempotency key is already bound to another opening payload.');
                }

                return $existing->load('terminal');
            }

            $terminal = Terminal::query()
                ->whereKey($data['terminal_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $terminal) {
                throw new DomainException('The selected terminal is unavailable.');
            }

            $existingUserShift = CashierShift::query()
                ->where('user_id', $actor->id)
                ->where('status', ShiftStatus::Open->value)
                ->lockForUpdate()
                ->first();

            if ($existingUserShift) {
                throw new DomainException('This user already has an open cashier shift.');
            }

            $existingTerminalShift = CashierShift::query()
                ->where('terminal_id', $terminal->id)
                ->where('status', ShiftStatus::Open->value)
                ->lockForUpdate()
                ->first();

            if ($existingTerminalShift) {
                throw new DomainException('The selected terminal already has an open cashier shift.');
            }

            $openingCash = Decimal::normalize($data['opening_cash'] ?? '0', 2);

            if (Decimal::isNegative($openingCash)) {
                throw new DomainException('Opening cash cannot be negative.');
            }

            $shift = CashierShift::create([
                'terminal_id' => $terminal->id,
                'user_id' => $actor->id,
                'open_idempotency_key' => $data['idempotency_key'],
                'opened_at' => now(),
                'opening_cash' => $openingCash,
                'expected_cash' => $openingCash,
                'status' => ShiftStatus::Open,
            ]);

            $this->cash->ensureOpeningMovement($shift, $actor);

            $this->audit->record(
                'cash.shift.opened',
                model: $shift,
                newValues: [
                    'terminal_id' => $terminal->id,
                    'opening_cash' => $openingCash,
                ],
                actor: $actor,
            );

            return $shift->fresh('terminal');
        });
    }
}
