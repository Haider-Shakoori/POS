<?php

namespace App\Models;

use App\Enums\GoodsReceiptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class GoodsReceipt extends Model
{
    protected $fillable = [
        'number',
        'supplier_id',
        'purchase_order_id',
        'created_by_user_id',
        'posted_by_user_id',
        'status',
        'idempotency_key',
        'supplier_invoice_reference',
        'received_at',
        'subtotal',
        'line_discount_total',
        'receipt_discount_amount',
        'expense_total',
        'net_total',
        'paid_amount',
        'balance_due',
        'posted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => GoodsReceiptStatus::class,
            'received_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'line_discount_total' => 'decimal:2',
            'receipt_discount_amount' => 'decimal:2',
            'expense_total' => 'decimal:2',
            'net_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Posted goods receipts are immutable and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('Posted goods receipts are immutable and cannot be deleted.');
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(GoodsReceiptExpense::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }
}
