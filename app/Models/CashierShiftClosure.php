<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CashierShiftClosure extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'idempotency_key',
        'cashier_shift_id',
        'version',
        'closed_by_user_id',
        'expected_cash',
        'actual_cash',
        'variance',
        'tolerance',
        'within_tolerance',
        'variance_reason',
        'closing_notes',
        'closed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_cash' => 'decimal:2',
            'actual_cash' => 'decimal:2',
            'variance' => 'decimal:2',
            'tolerance' => 'decimal:2',
            'within_tolerance' => 'boolean',
            'closed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Cashier shift closure snapshots are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Cashier shift closure snapshots are immutable.'));
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }
}
