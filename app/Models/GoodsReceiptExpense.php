<?php

namespace App\Models;

use App\Enums\PurchaseExpenseType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class GoodsReceiptExpense extends Model
{
    protected $fillable = [
        'goods_receipt_id',
        'type',
        'description',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'type' => PurchaseExpenseType::class,
            'amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Posted goods receipt expenses are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Posted goods receipt expenses are immutable.');
        });
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }
}
