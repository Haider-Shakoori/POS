<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountItem extends Model
{
    protected $fillable = [
        'stock_count_id',
        'product_id',
        'product_batch_id',
        'expected_quantity_base',
        'physical_quantity_base',
        'variance_quantity_base',
        'stock_movement_id',
        'cost_amount',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity_base' => 'decimal:6',
            'physical_quantity_base' => 'decimal:6',
            'variance_quantity_base' => 'decimal:6',
            'cost_amount' => 'decimal:4',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
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
