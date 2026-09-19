<?php

namespace App\Models;

use App\Enums\ShiftStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends Model
{
    protected $fillable = [
        'terminal_id',
        'user_id',
        'business_date',
        'open_idempotency_key',
        'opened_at',
        'closed_at',
        'closed_by_user_id',
        'opening_cash',
        'expected_cash',
        'actual_cash',
        'variance',
        'variance_within_tolerance',
        'variance_reason',
        'status',
        'closing_notes',
        'reopened_at',
        'reopened_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
            'opening_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'actual_cash' => 'decimal:2',
            'variance' => 'decimal:2',
            'variance_within_tolerance' => 'boolean',
            'status' => ShiftStatus::class,
        ];
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by_user_id');
    }

    public function closures(): HasMany
    {
        return $this->hasMany(CashierShiftClosure::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'cashier_shift_id');
    }
}
