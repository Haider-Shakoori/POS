<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class BusinessDayClosure extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'business_day_id',
        'idempotency_key',
        'number',
        'version',
        'closed_by_user_id',
        'shift_count',
        'sales_count',
        'sales_subtotal',
        'sales_line_discount_total',
        'sales_discount_total',
        'sales_net_total',
        'sales_return_total',
        'net_sales_total',
        'sales_cogs_total',
        'cogs_reversed_total',
        'net_cogs_total',
        'gross_profit_total',
        'customer_collections_total',
        'purchases_total',
        'purchase_returns_total',
        'supplier_payments_total',
        'operating_expenses_total',
        'other_income_total',
        'net_profit_total',
        'opening_cash_total',
        'cash_inflow_total',
        'cash_outflow_total',
        'expected_cash_total',
        'actual_cash_total',
        'variance_total',
        'cash_breakdown',
        'notes',
        'closed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'cash_breakdown' => 'array',
            'closed_at' => 'datetime',
            'created_at' => 'datetime',
            'sales_subtotal' => 'decimal:2',
            'sales_line_discount_total' => 'decimal:2',
            'sales_discount_total' => 'decimal:2',
            'sales_net_total' => 'decimal:2',
            'sales_return_total' => 'decimal:2',
            'net_sales_total' => 'decimal:2',
            'sales_cogs_total' => 'decimal:2',
            'cogs_reversed_total' => 'decimal:2',
            'net_cogs_total' => 'decimal:2',
            'gross_profit_total' => 'decimal:2',
            'customer_collections_total' => 'decimal:2',
            'purchases_total' => 'decimal:2',
            'purchase_returns_total' => 'decimal:2',
            'supplier_payments_total' => 'decimal:2',
            'operating_expenses_total' => 'decimal:2',
            'other_income_total' => 'decimal:2',
            'net_profit_total' => 'decimal:2',
            'opening_cash_total' => 'decimal:2',
            'cash_inflow_total' => 'decimal:2',
            'cash_outflow_total' => 'decimal:2',
            'expected_cash_total' => 'decimal:2',
            'actual_cash_total' => 'decimal:2',
            'variance_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Business-day closure snapshots are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Business-day closure snapshots are immutable.'));
    }

    public function businessDay(): BelongsTo
    {
        return $this->belongsTo(BusinessDay::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }
}
