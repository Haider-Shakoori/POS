<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CustomerCollectionAllocation extends Model
{
    protected $fillable = [
        'customer_collection_id',
        'sale_id',
        'amount',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Collection allocations are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Collection allocations are immutable.'));
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(CustomerCollection::class, 'customer_collection_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
