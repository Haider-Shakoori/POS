<?php

namespace App\Http\Requests\Closing;

use Illuminate\Foundation\Http\FormRequest;

class ReopenBusinessDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('business_days.reopen');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
