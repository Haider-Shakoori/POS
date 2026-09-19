<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'product_id',
        'product_unit_id',
        'stock_movement_key',
        'quantity',
        'conversion_factor',
        'quantity_base',
        'source_unit_cost',
        'line_subtotal',
        'line_discount_amount',
        'allocated_receipt_discount',
        'allocated_expense',
        'landed_total',
        'source_unit_landed_cost',
        'base_unit_landed_cost',
        'batch_number',
        'manufactured_at',
        'expires_at',
        'stock_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'conversion_factor' => 'decimal:6',
            'quantity_base' => 'decimal:6',
            'source_unit_cost' => 'decimal:4',
            'line_subtotal' => 'decimal:2',
            'line_discount_amount' => 'decimal:2',
            'allocated_receipt_discount' => 'decimal:2',
            'allocated_expense' => 'decimal:2',
            'landed_total' => 'decimal:2',
            'source_unit_landed_cost' => 'decimal:4',
            'base_unit_landed_cost' => 'decimal:4',
            'manufactured_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
