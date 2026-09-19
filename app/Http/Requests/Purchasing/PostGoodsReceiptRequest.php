<?php

namespace App\Http\Requests\Purchasing;

use App\Enums\PurchaseExpenseType;
use App\Enums\PurchasePaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PostGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user?->hasPermission('purchases.receive')) {
            return false;
        }

        if (! $this->filled('purchase_order_id')) {
            return $user->hasPermission('purchases.direct_receive');
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'supplier_invoice_reference' => ['nullable', 'string', 'max:120'],
            'received_at' => ['required', 'date'],
            'receipt_discount_amount' => ['nullable', 'decimal:0,2', 'min:0'],
            'paid_amount' => ['nullable', 'decimal:0,2', 'min:0'],
            'payment_method' => ['nullable', Rule::enum(PurchasePaymentMethod::class)],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'payment_notes' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'items.*.product_unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'items.*.quantity' => ['required', 'decimal:0,6', 'gt:0'],
            'items.*.unit_cost' => ['nullable', 'decimal:0,4', 'min:0'],
            'items.*.line_discount_amount' => ['nullable', 'decimal:0,2', 'min:0'],
            'items.*.batch_number' => ['nullable', 'string', 'max:100'],
            'items.*.manufactured_at' => ['nullable', 'date'],
            'items.*.expires_at' => ['nullable', 'date'],
            
            'expenses' => ['nullable', 'array'],
            'expenses.*.type' => ['required', Rule::enum(PurchaseExpenseType::class)],
            'expenses.*.description' => ['nullable', 'string', 'max:180'],
            'expenses.*.amount' => ['required', 'decimal:0,2', 'gt:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $paid = $this->input('paid_amount');

            if (
                $paid !== null
                && ! $validator->errors()->has('paid_amount')
                && bccomp((string) $paid, '0', 2) === 1
                && ! $this->filled('payment_method')
            ) {
                $validator->errors()->add('payment_method', __('ui.payment_method_required'));
            }

            $hasOrder = $this->filled('purchase_order_id');

            foreach ($this->input('items', []) as $index => $item) {
                if ($hasOrder && empty($item['purchase_order_item_id'])) {
                    $validator->errors()->add("items.$index.purchase_order_item_id", __('ui.po_item_required'));
                }

                if (! $hasOrder && empty($item['product_unit_id'])) {
                    $validator->errors()->add("items.$index.product_unit_id", __('ui.product_unit_required'));
                }

                if (
                    ! empty($item['manufactured_at'])
                    && ! empty($item['expires_at'])
                    && strtotime($item['expires_at']) < strtotime($item['manufactured_at'])
                ) {
                    $validator->errors()->add("items.$index.expires_at", __('ui.expiry_before_manufacture'));
                }
            }
        });
    }
}
