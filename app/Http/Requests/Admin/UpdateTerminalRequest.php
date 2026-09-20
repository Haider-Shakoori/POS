<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('settings.manage');
    }

    public function rules(): array
    {
        $terminalId = $this->route('terminal')?->getKey();

        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('terminals', 'code')->ignore($terminalId)],
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
