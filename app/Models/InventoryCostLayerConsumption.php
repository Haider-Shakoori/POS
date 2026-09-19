<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryCostLayerConsumption extends Model
{
    protected $fillable = [
        'inventory_cost_layer_id',
        'sale_item_id',
        'quantity_base',
        'unit_cost_base',
        'cost_amount',
        'cost_source',
    ];

    protected function casts(): array
    {
        return [
            'quantity_base' => 'decimal:6',
            'unit_cost_base' => 'decimal:4',
            'cost_amount' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Cost-layer consumptions are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Cost-layer consumptions are immutable.'));
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(InventoryCostLayer::class, 'inventory_cost_layer_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
