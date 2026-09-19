<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class HoldSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('sales.hold');
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'sale_discount_amount' => ['nullable', 'decimal:0,2', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_unit_id' => ['required', 'integer', 'distinct', 'exists:product_units,id'],
            'items.*.quantity' => ['required', 'decimal:0,6', 'gt:0'],
            'items.*.line_discount_amount' => ['nullable', 'decimal:0,2', 'min:0'],
        ];
    }
}
