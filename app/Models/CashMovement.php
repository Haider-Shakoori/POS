<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CashMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'idempotency_key',
        'cashier_shift_id',
        'terminal_id',
        'actor_user_id',
        'movement_type',
        'direction',
        'amount',
        'expected_cash_after',
        'source_type',
        'source_id',
        'reference_number',
        'reason',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expected_cash_after' => 'decimal:2',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Cash movements are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Cash movements are immutable.'));
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
