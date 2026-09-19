<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    protected $fillable = [
        'code',
        'entry_type',
        'name_en',
        'name_fa',
        'name_ps',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(OperatingEntry::class);
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $field = in_array($locale, ['fa', 'ps'], true) ? 'name_'.$locale : 'name_en';

        return $this->{$field} ?: $this->name_en;
    }
}
