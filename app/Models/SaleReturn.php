<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class SaleReturn extends Model
{
    protected $fillable = [
        'number',
        'idempotency_key',
        'sale_id',
        'created_by_user_id',
        'type',
        'status',
        'reason',
        'return_total',
        'cogs_reversed',
        'receivable_reversed',
        'refund_total',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'return_total' => 'decimal:2',
            'cogs_reversed' => 'decimal:2',
            'receivable_reversed' => 'decimal:2',
            'refund_total' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Posted sale returns are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Posted sale returns are immutable.'));
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(SaleRefund::class);
    }
}
