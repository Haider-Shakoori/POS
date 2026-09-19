<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SaleItemStockAllocation extends Model
{
    protected $fillable = [
        'sale_item_id',
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
        static::updating(fn (): never => throw new LogicException('Sale stock allocations are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Sale stock allocations are immutable.'));
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
