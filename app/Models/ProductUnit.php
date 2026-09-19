<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductUnit extends Model
{
    protected $fillable = [
        'product_id',
        'unit_id',
        'conversion_factor',
        'can_purchase',
        'can_sell',
        'selling_price',
        'minimum_selling_price',
        'wholesale_price',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:6',
            'can_purchase' => 'boolean',
            'can_sell' => 'boolean',
            'selling_price' => 'decimal:2',
            'minimum_selling_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }
}
