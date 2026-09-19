<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class PurchaseReturn extends Model
{
    protected $fillable = [
        'number',
        'idempotency_key',
        'goods_receipt_id',
        'supplier_id',
        'created_by_user_id',
        'reason',
        'return_total',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'return_total' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Posted purchase returns are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Posted purchase returns are immutable.'));
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
