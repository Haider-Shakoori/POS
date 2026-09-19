<?php

namespace App\Http\Requests\Inventory;

use App\Models\Unit;
use App\Support\Decimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('inventory.products.manage');
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'name_en' => ['required', 'string', 'max:200'],
            'name_fa' => ['nullable', 'string', 'max:200'],
            'name_ps' => ['nullable', 'string', 'max:200'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('is_active', true)],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')->where('is_active', true)],
            'base_unit_id' => ['required', Rule::exists('units', 'id')->where('is_active', true)],
            'description_en' => ['nullable', 'string'],
            'description_fa' => ['nullable', 'string'],
            'description_ps' => ['nullable', 'string'],
            'shelf_location' => ['nullable', 'string', 'max:100'],
            'purchase_cost' => ['nullable', 'decimal:0,2', 'min:0'],
            'selling_price' => ['required', 'decimal:0,2', 'min:0'],
            'minimum_selling_price' => ['nullable', 'decimal:0,2', 'min:0'],
            'wholesale_price' => ['nullable', 'decimal:0,2', 'min:0'],
            'minimum_stock' => ['nullable', 'decimal:0,6', 'min:0'],
            'reorder_quantity' => ['nullable', 'decimal:0,6', 'min:0'],
            'track_stock' => ['nullable', 'boolean'],
            'track_expiry' => ['nullable', 'boolean'],
            'units' => ['nullable', 'array'],
            'units.*.unit_id' => ['required', 'integer', 'distinct', Rule::exists('units', 'id')->where('is_active', true)],
            'units.*.conversion_factor' => ['required', 'decimal:0,6', 'gt:0'],
            'units.*.can_purchase' => ['nullable', 'boolean'],
            'units.*.can_sell' => ['nullable', 'boolean'],
            'units.*.selling_price' => ['nullable', 'decimal:0,2', 'min:0'],
            'units.*.minimum_selling_price' => ['nullable', 'decimal:0,2', 'min:0'],
            'units.*.wholesale_price' => ['nullable', 'decimal:0,2', 'min:0'],
            'barcodes' => ['nullable', 'array'],
            'barcodes.*.barcode' => ['required', 'string', 'max:191', 'distinct', 'unique:product_barcodes,barcode'],
            'barcodes.*.unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'barcodes.*.is_primary' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $baseUnitId = (int) $this->input('base_unit_id');
            $configuredUnitIds = [$baseUnitId];

            foreach ($this->input('units', []) as $index => $unit) {
                $unitId = (int) ($unit['unit_id'] ?? 0);

                if ($unitId === $baseUnitId) {
                    $validator->errors()->add("units.$index.unit_id", __('ui.base_unit_already_configured'));
                }

                $configuredUnitIds[] = $unitId;
            }

            foreach ($this->input('barcodes', []) as $index => $barcode) {
                if (! in_array((int) ($barcode['unit_id'] ?? 0), $configuredUnitIds, true)) {
                    $validator->errors()->add("barcodes.$index.unit_id", __('ui.barcode_unit_not_configured'));
                }
            }

            $primaryCount = collect($this->input('barcodes', []))
                ->filter(fn (array $barcode) => (bool) ($barcode['is_primary'] ?? false))
                ->count();

            if ($primaryCount > 1) {
                $validator->errors()->add('barcodes', __('ui.only_one_primary_barcode'));
            }

            if ($this->boolean('track_expiry') && ! $this->boolean('track_stock', true)) {
                $validator->errors()->add('track_expiry', __('ui.expiry_requires_stock_tracking'));
            }

            $minimumPrice = $this->input('minimum_selling_price');
            $sellingPrice = $this->input('selling_price');

            if (
                $minimumPrice !== null
                && $sellingPrice !== null
                && ! $validator->errors()->has('minimum_selling_price')
                && ! $validator->errors()->has('selling_price')
                && Decimal::compare($minimumPrice, $sellingPrice) > 0
            ) {
                $validator->errors()->add('minimum_selling_price', __('ui.minimum_price_not_above_sale'));
            }

            if (! $validator->errors()->has('base_unit_id')) {
                $baseUnit = Unit::query()->find($baseUnitId);

                if ($baseUnit) {
                    foreach (['minimum_stock', 'reorder_quantity'] as $field) {
                        if (
                            $this->filled($field)
                            && ! $validator->errors()->has($field)
                            && Decimal::fractionalDigits($this->input($field)) > $baseUnit->decimal_places
                        ) {
                            $validator->errors()->add($field, __('ui.quantity_precision_invalid', [
                                'places' => $baseUnit->decimal_places,
                            ]));
                        }
                    }
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'track_stock' => $this->boolean('track_stock'),
            'track_expiry' => $this->boolean('track_expiry'),
        ]);
    }
}
