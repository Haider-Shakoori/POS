<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeldSaleItem extends Model
{
    protected $fillable = [
        'held_sale_id',
        'product_unit_id',
        'product_name_snapshot',
        'sku_snapshot',
        'unit_name_snapshot',
        'quantity',
        'line_discount_amount',
        'unit_price_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'line_discount_amount' => 'decimal:2',
            'unit_price_snapshot' => 'decimal:2',
        ];
    }

    public function heldSale(): BelongsTo
    {
        return $this->belongsTo(HeldSale::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }
}
