<?php

namespace App\Services\Sales;

use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use App\Support\Decimal;
use Illuminate\Support\Collection;

class ProductSearchService
{
    public function __construct(private readonly SalesPricingService $pricing)
    {
    }

    public function search(string $term, int $limit = 20): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return collect();
        }

        $exactBarcode = ProductBarcode::query()
            ->with(['productUnit.product', 'productUnit.unit'])
            ->where('barcode', $term)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->whereHas('productUnit', fn ($query) => $query->where('can_sell', true))
            ->first();

        if ($exactBarcode) {
            return collect([$this->transform($exactBarcode->productUnit, $exactBarcode->barcode)]);
        }

        $productUnits = ProductUnit::query()
            ->with(['product.barcodes', 'unit'])
            ->where('can_sell', true)
            ->whereHas('product', function ($query) use ($term): void {
                $query->where('is_active', true)
                    ->where(function ($query) use ($term): void {
                        $query->where('sku', 'like', "%{$term}%")
                            ->orWhere('name_en', 'like', "%{$term}%")
                            ->orWhere('name_fa', 'like', "%{$term}%")
                            ->orWhere('name_ps', 'like', "%{$term}%")
                            ->orWhereHas('barcodes', fn ($query) => $query->where('barcode', 'like', "%{$term}%"));
                    });
            })
            ->limit($limit)
            ->get();

        return $productUnits
            ->map(fn (ProductUnit $productUnit) => $this->transform($productUnit))
            ->values();
    }

    private function transform(ProductUnit $productUnit, ?string $matchedBarcode = null): array
    {
        $prices = $this->pricing->resolve($productUnit);
        $product = $productUnit->product;

        return [
            'product_unit_id' => $productUnit->id,
            'product_id' => $product->id,
            'name' => $product->localizedName(),
            'name_en' => $product->name_en,
            'sku' => $product->sku,
            'unit' => $productUnit->unit->localizedName(),
            'unit_code' => $productUnit->unit->code,
            'decimal_places' => $productUnit->unit->decimal_places,
            'conversion_factor' => $productUnit->conversion_factor,
            'price' => $prices['price'],
            'minimum_price' => $prices['minimum_price'],
            'stock_on_hand' => $product->stock_on_hand,
            'available_quantity' => $product->track_stock
                ? Decimal::divide($product->stock_on_hand, $productUnit->conversion_factor)
                : null,
            'track_stock' => $product->track_stock,
            'track_expiry' => $product->track_expiry,
            'matched_barcode' => $matchedBarcode,
            'barcodes' => $product->barcodes
                ->where('product_unit_id', $productUnit->id)
                ->pluck('barcode')
                ->values()
                ->all(),
        ];
    }
}
