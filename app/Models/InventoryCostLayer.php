<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCostLayer extends Model
{
    protected $fillable = [
        'product_id',
        'product_batch_id',
        'source_stock_movement_id',
        'initial_quantity_base',
        'remaining_quantity_base',
        'unit_cost_base',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'initial_quantity_base' => 'decimal:6',
            'remaining_quantity_base' => 'decimal:6',
            'unit_cost_base' => 'decimal:4',
            'received_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    public function sourceStockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'source_stock_movement_id');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(InventoryCostLayerConsumption::class);
    }
}
