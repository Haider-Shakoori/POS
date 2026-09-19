<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryWriteoffItem extends Model
{
    protected $fillable = [
        'inventory_writeoff_id',
        'product_id',
        'product_batch_id',
        'stock_movement_id',
        'quantity_base',
        'cost_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity_base' => 'decimal:6',
            'cost_amount' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Posted inventory write-off items are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Posted inventory write-off items are immutable.'));
    }

    public function writeoff(): BelongsTo
    {
        return $this->belongsTo(InventoryWriteoff::class, 'inventory_writeoff_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
