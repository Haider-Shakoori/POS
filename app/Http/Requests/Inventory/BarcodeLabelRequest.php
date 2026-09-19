<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class BarcodeLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('inventory.view');
    }

    public function rules(): array
    {
        return [
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'autoprint' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('autoprint')) {
            $this->merge(['autoprint' => $this->boolean('autoprint')]);
        }
    }
}
