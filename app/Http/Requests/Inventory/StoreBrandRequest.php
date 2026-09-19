<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('inventory.catalog.manage');
    }

    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:160', 'unique:brands,name_en'],
            'name_fa' => ['nullable', 'string', 'max:160'],
            'name_ps' => ['nullable', 'string', 'max:160'],
        ];
    }
}
