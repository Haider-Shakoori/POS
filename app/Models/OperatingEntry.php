<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OperatingEntry extends Model
{
    protected $fillable = [
        'number',
        'idempotency_key',
        'expense_category_id',
        'payment_method_id',
        'recorded_by_user_id',
        'entry_type',
        'amount',
        'reference',
        'description',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Operating entries are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Operating entries are immutable.'));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
