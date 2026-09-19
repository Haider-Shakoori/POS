<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('inventory.catalog.manage');
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', Rule::exists('categories', 'id')->where('is_active', true)],
            'name_en' => ['required', 'string', 'max:160'],
            'name_fa' => ['nullable', 'string', 'max:160'],
            'name_ps' => ['nullable', 'string', 'max:160'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
