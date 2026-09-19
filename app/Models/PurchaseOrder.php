<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'number',
        'supplier_id',
        'created_by_user_id',
        'approved_by_user_id',
        'status',
        'order_date',
        'expected_date',
        'supplier_reference',
        'subtotal',
        'line_discount_total',
        'order_discount_amount',
        'net_total',
        'approved_at',
        'cancelled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'order_date' => 'date',
            'expected_date' => 'date',
            'subtotal' => 'decimal:2',
            'line_discount_total' => 'decimal:2',
            'order_discount_amount' => 'decimal:2',
            'net_total' => 'decimal:2',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function isReceivable(): bool
    {
        return in_array($this->status, [
            PurchaseOrderStatus::Approved,
            PurchaseOrderStatus::PartiallyReceived,
        ], true);
    }
}
