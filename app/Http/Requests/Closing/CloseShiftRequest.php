<?php

namespace App\Http\Requests\Closing;

use Illuminate\Foundation\Http\FormRequest;

class CloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('shifts.close');
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'actual_cash' => ['required', 'decimal:0,2', 'min:0'],
            'variance_reason' => ['nullable', 'string', 'max:1000'],
            'closing_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
