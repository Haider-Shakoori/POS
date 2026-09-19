<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('customers.collect');
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'tendered_amount' => ['nullable', 'decimal:0,2', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:120'],
            'collected_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
