<?php

namespace App\Http\Requests\Cash;

use Illuminate\Foundation\Http\FormRequest;

class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('shifts.open');
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'terminal_id' => ['required', 'integer', 'exists:terminals,id'],
            'opening_cash' => ['required', 'decimal:0,2', 'min:0'],
        ];
    }
}
