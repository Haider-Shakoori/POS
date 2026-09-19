<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class SaleReturnItem extends Model
{
    protected $fillable = [
        'sale_return_id',
        'sale_item_id',
        'quantity',
        'quantity_base',
        'return_amount',
        'cogs_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'quantity_base' => 'decimal:6',
            'return_amount' => 'decimal:2',
            'cogs_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Posted sale return items are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Posted sale return items are immutable.'));
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function stockRestorations(): HasMany
    {
        return $this->hasMany(SaleReturnStockAllocation::class);
    }

    public function costRestorations(): HasMany
    {
        return $this->hasMany(InventoryCostLayerRestoration::class);
    }
}
