<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    protected $fillable = [
        'code',
        'name_en',
        'name_fa',
        'name_ps',
        'is_cash',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_cash' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function salePayments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(CustomerCollection::class);
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $field = in_array($locale, ['fa', 'ps'], true) ? 'name_'.$locale : 'name_en';

        return $this->{$field} ?: $this->name_en;
    }
}
