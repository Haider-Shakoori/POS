<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeldSale extends Model
{
    protected $fillable = [
        'number',
        'idempotency_key',
        'cashier_user_id',
        'customer_id',
        'customer_name_snapshot',
        'sale_discount_amount',
        'status',
        'notes',
        'held_at',
        'resumed_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_discount_amount' => 'decimal:2',
            'held_at' => 'datetime',
            'resumed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(HeldSaleItem::class);
    }
}
