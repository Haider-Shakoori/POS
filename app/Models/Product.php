<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'sku',
        'name_en',
        'name_fa',
        'name_ps',
        'category_id',
        'brand_id',
        'base_unit_id',
        'description_en',
        'description_fa',
        'description_ps',
        'shelf_location',
        'image_path',
        'purchase_cost',
        'selling_price',
        'minimum_selling_price',
        'wholesale_price',
        'stock_on_hand',
        'minimum_stock',
        'reorder_quantity',
        'track_stock',
        'track_expiry',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_cost' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'minimum_selling_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'stock_on_hand' => 'decimal:6',
            'minimum_stock' => 'decimal:6',
            'reorder_quantity' => 'decimal:6',
            'track_stock' => 'boolean',
            'track_expiry' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $field = in_array($locale, ['fa', 'ps'], true) ? 'name_'.$locale : 'name_en';

        return $this->{$field} ?: $this->name_en;
    }
}
