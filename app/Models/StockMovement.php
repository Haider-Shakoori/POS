<?php

namespace App\Models;

use App\Enums\StockMovementType;
use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'product_batch_id',
        'source_unit_id',
        'actor_user_id',
        'movement_type',
        'source_quantity',
        'conversion_factor',
        'quantity_base',
        'balance_after',
        'batch_balance_after',
        'unit_cost',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'notes',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => StockMovementType::class,
            'source_quantity' => 'decimal:6',
            'conversion_factor' => 'decimal:6',
            'quantity_base' => 'decimal:6',
            'balance_after' => 'decimal:6',
            'batch_balance_after' => 'decimal:6',
            'unit_cost' => 'decimal:4',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Stock movements are append-only and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('Stock movements are append-only and cannot be deleted.');
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    public function sourceUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'source_unit_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
