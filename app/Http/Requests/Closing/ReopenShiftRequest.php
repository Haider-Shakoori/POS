<?php

namespace App\Http\Requests\Closing;

use Illuminate\Foundation\Http\FormRequest;

class ReopenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('shifts.reopen');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
