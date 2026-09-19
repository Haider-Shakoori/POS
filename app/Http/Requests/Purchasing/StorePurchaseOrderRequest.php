<?php

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('purchases.create');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'supplier_reference' => ['nullable', 'string', 'max:120'],
            'order_discount_amount' => ['nullable', 'decimal:0,2', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_unit_id' => ['required', 'integer', 'distinct', 'exists:product_units,id'],
            'items.*.quantity' => ['required', 'decimal:0,6', 'gt:0'],
            'items.*.unit_cost' => ['required', 'decimal:0,4', 'min:0'],
            'items.*.line_discount_amount' => ['nullable', 'decimal:0,2', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
