<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SalePayment extends Model
{
    protected $fillable = [
        'idempotency_key',
        'sale_id',
        'customer_id',
        'payment_method_id',
        'recorded_by_user_id',
        'applied_amount',
        'tendered_amount',
        'change_amount',
        'reference',
        'source_type',
        'source_id',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'applied_amount' => 'decimal:2',
            'tendered_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Sale payments are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Sale payments are immutable.'));
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
