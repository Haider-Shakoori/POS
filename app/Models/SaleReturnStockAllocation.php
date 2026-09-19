<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SaleReturnStockAllocation extends Model
{
    protected $fillable = [
        'sale_return_item_id',
        'original_sale_stock_allocation_id',
        'product_batch_id',
        'stock_movement_id',
        'quantity_base',
    ];

    protected function casts(): array
    {
        return ['quantity_base' => 'decimal:6'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Return stock allocations are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Return stock allocations are immutable.'));
    }

    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(SaleReturnItem::class, 'sale_return_item_id');
    }

    public function originalAllocation(): BelongsTo
    {
        return $this->belongsTo(SaleItemStockAllocation::class, 'original_sale_stock_allocation_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
