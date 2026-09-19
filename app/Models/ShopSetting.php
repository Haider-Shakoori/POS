<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopSetting extends Model
{
    protected $fillable = [
        'shop_name',
        'address',
        'phone',
        'logo_path',
        'default_locale',
        'receipt_locale',
        'receipt_size',
        'cash_variance_tolerance',
        'negative_stock_enabled',
        'discount_approval_threshold',
    ];

    protected function casts(): array
    {
        return [
            'cash_variance_tolerance' => 'decimal:2',
            'discount_approval_threshold' => 'decimal:2',
            'negative_stock_enabled' => 'boolean',
        ];
    }
}
