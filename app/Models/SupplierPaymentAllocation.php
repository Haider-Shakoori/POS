<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SupplierPaymentAllocation extends Model
{
    protected $fillable = [
        'supplier_payment_id',
        'goods_receipt_id',
        'amount',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Supplier payment allocations are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Supplier payment allocations are immutable.'));
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id');
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }
}
