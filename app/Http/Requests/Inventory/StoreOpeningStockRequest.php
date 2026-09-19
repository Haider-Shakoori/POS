<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOpeningStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('inventory.opening_stock');
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'decimal:0,6', 'gt:0'],
            'unit_id' => ['required', Rule::exists('units', 'id')->where('is_active', true)],
            'unit_cost' => ['nullable', 'decimal:0,4', 'min:0'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'manufactured_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:manufactured_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $product = $this->route('product');

            if ($product?->track_expiry && ! $this->filled('batch_number')) {
                $validator->errors()->add('batch_number', __('ui.batch_required_for_expiry'));
            }

            if ($product?->track_expiry && ! $this->filled('expires_at')) {
                $validator->errors()->add('expires_at', __('ui.expiry_date_required'));
            }

            if ($product && ! $product->productUnits()->where('unit_id', $this->integer('unit_id'))->exists()) {
                $validator->errors()->add('unit_id', __('ui.unit_not_configured_for_product'));
            }
        });
    }
}
