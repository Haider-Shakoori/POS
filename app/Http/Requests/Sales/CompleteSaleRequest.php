<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class CompleteSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('sales.create');
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

            'payments' => ['nullable', 'array', 'max:8'],
            'payments.*.payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'decimal:0,2', 'gt:0'],
            'payments.*.tendered_amount' => ['nullable', 'decimal:0,2', 'gt:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:120'],
            'payments.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
