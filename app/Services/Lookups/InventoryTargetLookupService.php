<?php

namespace App\Services\Lookups;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class InventoryTargetLookupService
{
    public const MODES = ['count', 'damage', 'expiry'];

    public function search(string $term, string $mode, int $limit = 15): Collection
    {
        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException('Unsupported inventory target mode.');
        }

        $term = trim($term);
        $limit = min(max($limit, 1), 25);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        $rows = collect();

        if ($mode !== 'expiry') {
            $products = $this->regularProducts($term, $mode, $limit);

            $rows->push(...$products->map(fn (Product $product): array => $this->productRow($product)));
        }

        if ($rows->count() < $limit) {
            $batches = $this->batches($term, $mode, $limit - $rows->count());

            $rows->push(...$batches->map(fn (ProductBatch $batch): array => $this->batchRow($batch)));
        }

        return $rows
            ->sortBy(fn (array $row): string => mb_strtolower($row['label']))
            ->take($limit)
            ->values();
    }

    private function regularProducts(string $term, string $mode, int $limit): Collection
    {
        $prefix = $this->prefix($term);

        $query = Product::query()
            ->with('baseUnit:id,symbol')
            ->where('is_active', true)
            ->where('track_stock', true)
            ->where('track_expiry', false)
            ->where(function (Builder $builder) use ($term, $prefix): void {
                $builder->where('sku', $term)
                    ->orWhere('sku', 'like', $prefix)
                    ->orWhere('name_en', 'like', $prefix)
                    ->orWhere('name_fa', 'like', $prefix)
                    ->orWhere('name_ps', 'like', $prefix);
            });

        if ($mode === 'damage') {
            $query->where('stock_on_hand', '>', 0);
        }

        return $query
            ->orderBy('name_en')
            ->limit($limit)
            ->get([
                'id',
                'base_unit_id',
                'sku',
                'name_en',
                'name_fa',
                'name_ps',
                'stock_on_hand',
            ]);
    }

    private function batches(string $term, string $mode, int $limit): Collection
    {
        $prefix = $this->prefix($term);

        $query = ProductBatch::query()
            ->with('product.baseUnit:id,symbol')
            ->whereHas('product', function (Builder $product) use ($term, $prefix, $mode): void {
                if ($mode !== 'expiry') {
                    $product->where('is_active', true);
                }

                $product->where('track_stock', true)
                    ->where('track_expiry', true)
                    ->where(function (Builder $builder) use ($term, $prefix): void {
                        $builder->where('sku', $term)
                            ->orWhere('sku', 'like', $prefix)
                            ->orWhere('name_en', 'like', $prefix)
                            ->orWhere('name_fa', 'like', $prefix)
                            ->orWhere('name_ps', 'like', $prefix);
                    });
            })
            ->where(function (Builder $builder) use ($prefix): void {
                $builder->where('batch_number', 'like', $prefix)
                    ->orWhereHas('product', function (Builder $product) use ($prefix): void {
                        $product->where('sku', 'like', $prefix)
                            ->orWhere('name_en', 'like', $prefix)
                            ->orWhere('name_fa', 'like', $prefix)
                            ->orWhere('name_ps', 'like', $prefix);
                    });
            });

        if (in_array($mode, ['damage', 'expiry'], true)) {
            $query->where('stock_on_hand', '>', 0);
        }

        if ($mode === 'expiry') {
            $query->whereDate('expires_at', '<', today());
        }

        return $query
            ->orderBy('expires_at')
            ->orderBy('batch_number')
            ->limit($limit)
            ->get([
                'id',
                'product_id',
                'batch_number',
                'expires_at',
                'stock_on_hand',
            ]);
    }

    private function productRow(Product $product): array
    {
        return [
            'value' => $product->id.':',
            'label' => $product->localizedName(),
            'meta' => $product->sku,
            'stock' => Decimal::normalize($product->stock_on_hand),
            'unit' => $product->baseUnit?->symbol,
            'expires_at' => null,
        ];
    }

    private function batchRow(ProductBatch $batch): array
    {
        return [
            'value' => $batch->product_id.':'.$batch->id,
            'label' => $batch->product->localizedName().' · '.$batch->batch_number,
            'meta' => $batch->product->sku,
            'stock' => Decimal::normalize($batch->stock_on_hand),
            'unit' => $batch->product->baseUnit?->symbol,
            'expires_at' => $batch->expires_at?->format('Y-m-d'),
        ];
    }

    private function prefix(string $term): string
    {
        return addcslashes($term, '%_').'%' ;
    }
}
