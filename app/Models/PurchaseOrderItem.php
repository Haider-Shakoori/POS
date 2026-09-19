<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_unit_id',
        'ordered_quantity',
        'received_quantity',
        'unit_cost',
        'line_subtotal',
        'line_discount_amount',
        'line_net_total',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'decimal:6',
            'received_quantity' => 'decimal:6',
            'unit_cost' => 'decimal:4',
            'line_subtotal' => 'decimal:2',
            'line_discount_amount' => 'decimal:2',
            'line_net_total' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }
}
