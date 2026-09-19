<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'product_unit_id',
        'product_name_snapshot',
        'sku_snapshot',
        'unit_name_snapshot',
        'quantity',
        'conversion_factor',
        'quantity_base',
        'unit_price',
        'minimum_unit_price',
        'line_subtotal',
        'line_discount_amount',
        'allocated_sale_discount',
        'line_net_total',
        'cogs_amount',
        'gross_profit',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'conversion_factor' => 'decimal:6',
            'quantity_base' => 'decimal:6',
            'unit_price' => 'decimal:2',
            'minimum_unit_price' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'line_discount_amount' => 'decimal:2',
            'allocated_sale_discount' => 'decimal:2',
            'line_net_total' => 'decimal:2',
            'cogs_amount' => 'decimal:2',
            'gross_profit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Completed sale items are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Completed sale items are immutable.');
        });
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function stockAllocations(): HasMany
    {
        return $this->hasMany(SaleItemStockAllocation::class);
    }

    public function costConsumptions(): HasMany
    {
        return $this->hasMany(InventoryCostLayerConsumption::class);
    }
}
