<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShopSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('settings.manage');
    }

    public function rules(): array
    {
        return [
            'shop_name' => ['required', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'default_locale' => ['required', Rule::in(['en', 'fa', 'ps'])],
            'receipt_locale' => ['required', Rule::in(['en', 'fa', 'ps'])],
            'receipt_size' => ['required', Rule::in(['57mm', '80mm'])],
            'cash_variance_tolerance' => ['required', 'decimal:0,2', 'min:0'],
            'negative_stock_enabled' => ['required', 'boolean'],
            'discount_approval_threshold' => ['nullable', 'decimal:0,2', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'negative_stock_enabled' => $this->boolean('negative_stock_enabled'),
        ]);
    }
}
