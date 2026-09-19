<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryCostLayerRestoration extends Model
{
    protected $fillable = [
        'sale_return_item_id',
        'original_consumption_id',
        'inventory_cost_layer_id',
        'quantity_base',
        'unit_cost_base',
        'cost_amount',
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
        static::updating(fn (): never => throw new LogicException('Cost-layer restorations are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Cost-layer restorations are immutable.'));
    }

    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(SaleReturnItem::class, 'sale_return_item_id');
    }

    public function originalConsumption(): BelongsTo
    {
        return $this->belongsTo(InventoryCostLayerConsumption::class, 'original_consumption_id');
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(InventoryCostLayer::class, 'inventory_cost_layer_id');
    }
}
