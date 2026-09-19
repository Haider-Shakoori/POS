<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PurchaseReturnItem extends Model
{
    protected $fillable = [
        'purchase_return_id',
        'goods_receipt_item_id',
        'inventory_cost_layer_id',
        'stock_movement_id',
        'quantity',
        'quantity_base',
        'return_amount',
        'unit_cost_base',
        'cost_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'quantity_base' => 'decimal:6',
            'return_amount' => 'decimal:2',
            'unit_cost_base' => 'decimal:4',
            'cost_amount' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Posted purchase return items are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Posted purchase return items are immutable.'));
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function goodsReceiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }

    public function costLayer(): BelongsTo
    {
        return $this->belongsTo(InventoryCostLayer::class, 'inventory_cost_layer_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
