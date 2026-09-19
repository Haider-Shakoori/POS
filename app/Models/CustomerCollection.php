<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CustomerCollection extends Model
{
    protected $fillable = [
        'number',
        'idempotency_key',
        'customer_id',
        'payment_method_id',
        'recorded_by_user_id',
        'amount',
        'tendered_amount',
        'change_amount',
        'reference',
        'collected_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tendered_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'collected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Customer collections are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Customer collections are immutable.'));
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

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerCollectionAllocation::class);
    }
}
