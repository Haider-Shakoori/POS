<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryWriteoffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('inventory.writeoff');
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'writeoff_type' => ['required', Rule::in(['damage', 'expiry'])],
            'reason' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.target' => ['required', 'string', 'regex:/^\d+:\d*$/'],
            'items.*.quantity_base' => ['required', 'decimal:0,6', 'gt:0'],
        ];
    }
}
