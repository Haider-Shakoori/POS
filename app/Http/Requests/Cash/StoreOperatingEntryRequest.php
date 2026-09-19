<?php

namespace App\Http\Requests\Cash;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOperatingEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('expenses.create');
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'entry_type' => ['required', Rule::in(['expense', 'income'])],
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
