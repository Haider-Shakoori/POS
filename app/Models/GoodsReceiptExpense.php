<?php

namespace App\Models;

use App\Enums\PurchaseExpenseType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }
}
