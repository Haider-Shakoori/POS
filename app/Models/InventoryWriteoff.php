<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class InventoryWriteoff extends Model
{
    protected $fillable = [
        'number',
        'idempotency_key',
        'writeoff_type',
        'posted_by_user_id',
        'reason',
        'total_cost',
        'posted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_cost' => 'decimal:4',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Posted inventory write-offs are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Posted inventory write-offs are immutable.'));
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryWriteoffItem::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }
}
