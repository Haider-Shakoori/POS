<?php

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('suppliers.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'contact_person' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:50'],
            'alternate_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'opening_balance' => ['nullable', 'decimal:0,2', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
