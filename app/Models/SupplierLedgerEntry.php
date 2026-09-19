<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SupplierLedgerEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'supplier_id',
        'actor_user_id',
        'entry_type',
        'debit',
        'credit',
        'balance_after',
        'reference_type',
        'reference_id',
        'reference_number',
        'occurred_at',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Supplier ledger entries are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Supplier ledger entries are immutable.'));
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
