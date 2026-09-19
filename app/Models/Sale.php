<?php

namespace App\Models;

use App\Enums\SalePaymentStatus;
use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Sale extends Model
{
    protected $fillable = [
        'number',
        'idempotency_key',
        'cashier_user_id',
        'terminal_id',
        'cashier_shift_id',
        'status',
        'payment_status',
        'customer_name_snapshot',
        'subtotal',
        'line_discount_total',
        'sale_discount_amount',
        'net_total',
        'cogs_total',
        'gross_profit',
        'paid_amount',
        'balance_due',
        'sold_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'payment_status' => SalePaymentStatus::class,
            'subtotal' => 'decimal:2',
            'line_discount_total' => 'decimal:2',
            'sale_discount_amount' => 'decimal:2',
            'net_total' => 'decimal:2',
            'cogs_total' => 'decimal:2',
            'gross_profit' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'sold_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Sale $sale): void {
            $allowed = [
                'payment_status',
                'paid_amount',
                'balance_due',
                'updated_at',
            ];

            $forbidden = array_diff(array_keys($sale->getDirty()), $allowed);

            if ($forbidden !== []) {
                throw new LogicException('Completed sale commercial fields are immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Completed sales are immutable and cannot be deleted.');
        });
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function cashierShift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
