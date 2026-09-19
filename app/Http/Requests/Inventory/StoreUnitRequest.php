<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('inventory.catalog.manage');
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'alpha_dash:ascii', 'unique:units,code'],
            'name_en' => ['required', 'string', 'max:100'],
            'name_fa' => ['nullable', 'string', 'max:100'],
            'name_ps' => ['nullable', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:30'],
            'decimal_places' => ['required', 'integer', 'between:0,6'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => strtoupper((string) $this->input('code'))]);
        }
    }
}
