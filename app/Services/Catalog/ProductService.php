<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function create(array $data, ?User $actor = null): Product
    {
        return DB::transaction(function () use ($data, $actor): Product {
            $product = Product::create([
                'sku' => $data['sku'],
                'name_en' => $data['name_en'],
                'name_fa' => $data['name_fa'] ?? null,
                'name_ps' => $data['name_ps'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'brand_id' => $data['brand_id'] ?? null,
                'base_unit_id' => $data['base_unit_id'],
                'description_en' => $data['description_en'] ?? null,
                'description_fa' => $data['description_fa'] ?? null,
                'description_ps' => $data['description_ps'] ?? null,
                'shelf_location' => $data['shelf_location'] ?? null,
                'purchase_cost' => $data['purchase_cost'] ?? '0',
                'selling_price' => $data['selling_price'],
                'minimum_selling_price' => $data['minimum_selling_price'] ?? null,
                'wholesale_price' => $data['wholesale_price'] ?? null,
                'minimum_stock' => $data['minimum_stock'] ?? '0',
                'reorder_quantity' => $data['reorder_quantity'] ?? '0',
                'track_stock' => $data['track_stock'] ?? true,
                'track_expiry' => $data['track_expiry'] ?? false,
                'is_active' => true,
            ]);

            $productUnits = [];

            $baseProductUnit = ProductUnit::create([
                'product_id' => $product->id,
                'unit_id' => $product->base_unit_id,
                'conversion_factor' => Decimal::normalize('1'),
                'can_purchase' => true,
                'can_sell' => true,
                'selling_price' => $product->selling_price,
                'minimum_selling_price' => $product->minimum_selling_price,
                'wholesale_price' => $product->wholesale_price,
            ]);

            $productUnits[$product->base_unit_id] = $baseProductUnit;

            foreach ($data['units'] ?? [] as $unitData) {
                $unitId = (int) $unitData['unit_id'];

                if ($unitId === $product->base_unit_id || isset($productUnits[$unitId])) {
                    throw ValidationException::withMessages([
                        'units' => __('ui.duplicate_product_unit'),
                    ]);
                }

                $factor = Decimal::normalize($unitData['conversion_factor']);

                if (! Decimal::isPositive($factor)) {
                    throw ValidationException::withMessages([
                        'units' => __('ui.conversion_factor_positive'),
                    ]);
                }

                $productUnits[$unitId] = ProductUnit::create([
                    'product_id' => $product->id,
                    'unit_id' => $unitId,
                    'conversion_factor' => $factor,
                    'can_purchase' => (bool) ($unitData['can_purchase'] ?? false),
                    'can_sell' => (bool) ($unitData['can_sell'] ?? false),
                    'selling_price' => $unitData['selling_price'] ?? null,
                    'minimum_selling_price' => $unitData['minimum_selling_price'] ?? null,
                    'wholesale_price' => $unitData['wholesale_price'] ?? null,
                ]);
            }

            $barcodes = $data['barcodes'] ?? [];
            $hasPrimary = collect($barcodes)->contains(fn (array $barcode) => (bool) ($barcode['is_primary'] ?? false));

            foreach ($barcodes as $index => $barcodeData) {
                $unitId = (int) $barcodeData['unit_id'];

                if (! isset($productUnits[$unitId])) {
                    throw ValidationException::withMessages([
                        'barcodes' => __('ui.barcode_unit_not_configured'),
                    ]);
                }

                ProductBarcode::create([
                    'product_id' => $product->id,
                    'product_unit_id' => $productUnits[$unitId]->id,
                    'barcode' => $barcodeData['barcode'],
                    'is_primary' => $hasPrimary
                        ? (bool) ($barcodeData['is_primary'] ?? false)
                        : $index === 0,
                ]);
            }

            $this->audit->record(
                'inventory.product.created',
                model: $product,
                newValues: [
                    'sku' => $product->sku,
                    'base_unit_id' => $product->base_unit_id,
                    'track_stock' => $product->track_stock,
                    'track_expiry' => $product->track_expiry,
                ],
                actor: $actor,
            );

            return $product->fresh(['baseUnit', 'productUnits.unit', 'barcodes']);
        });
    }
}
