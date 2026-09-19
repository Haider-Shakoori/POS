<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class VoidSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('sales.void');
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'max:500'],
            'refund_payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'refund_reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
