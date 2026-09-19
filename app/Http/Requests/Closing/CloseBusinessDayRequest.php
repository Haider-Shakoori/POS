<?php

namespace App\Http\Requests\Closing;

use Illuminate\Foundation\Http\FormRequest;

class CloseBusinessDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('business_days.close');
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
