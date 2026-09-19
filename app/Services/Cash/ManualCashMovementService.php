<?php

namespace App\Services\Cash;

use App\Models\CashierShift;
use App\Models\CashMovement;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

class ManualCashMovementService
{
    public function __construct(
        private readonly CashMovementService $cash,
        private readonly AuditLogger $audit,
    ) {
    }

    public function record(CashierShift $shift, array $data, User $actor): CashMovement
    {
        return DB::transaction(function () use ($shift, $data, $actor): CashMovement {
            if (! $actor->hasPermission('cash.manage')) {
                throw new DomainException('The user is not allowed to manage drawer cash.');
            }

            $movement = $this->cash->recordManual(
                actor: $actor,
                shift: $shift,
                idempotencyKey: $data['idempotency_key'],
                movementType: $data['movement_type'],
                amount: $data['amount'],
                reason: $data['reason'],
            );

            $this->audit->record(
                'cash.manual_movement.recorded',
                model: $movement,
                newValues: [
                    'movement_type' => $movement->movement_type,
                    'direction' => $movement->direction,
                    'amount' => $movement->amount,
                    'cashier_shift_id' => $movement->cashier_shift_id,
                ],
                actor: $actor,
            );

            return $movement;
        });
    }
}
