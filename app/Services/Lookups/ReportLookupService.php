<?php

namespace App\Services\Lookups;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ReportLookupService
{
    public const TYPES = ['product', 'category', 'customer', 'supplier'];

    public function search(string $type, string $term, int $limit = 15): Collection
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unsupported report lookup type.');
        }

        $term = trim($term);
        $limit = min(max($limit, 1), 25);

        return match ($type) {
            'product' => $this->products($term, $limit),
            'category' => $this->categories($term, $limit),
            'customer' => $this->customers($term, $limit),
            'supplier' => $this->suppliers($term, $limit),
        };
    }

    public function selected(array $filters): array
    {
        return [
            'product_id' => $this->selectedProduct($filters['product_id'] ?? null),
            'category_id' => $this->selectedCategory($filters['category_id'] ?? null),
            'customer_id' => $this->selectedCustomer($filters['customer_id'] ?? null),
            'supplier_id' => $this->selectedSupplier($filters['supplier_id'] ?? null),
        ];
    }

    private function products(string $term, int $limit): Collection
    {
        $query = Product::query()
            ->where('is_active', true)
            ->select(['id', 'sku', 'name_en', 'name_fa', 'name_ps']);

        $this->applyProductSearch($query, $term);

        return $query
            ->orderBy('name_en')
            ->limit($limit)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'label' => $product->localizedName(),
                'meta' => $product->sku,
            ])
            ->values();
    }

    private function categories(string $term, int $limit): Collection
    {
        $query = Category::query()
            ->where('is_active', true)
            ->select(['id', 'name_en', 'name_fa', 'name_ps']);

        if ($term !== '') {
            $prefix = $this->prefix($term);
            $query->where(function (Builder $builder) use ($prefix): void {
                $builder->where('name_en', 'like', $prefix)
                    ->orWhere('name_fa', 'like', $prefix)
                    ->orWhere('name_ps', 'like', $prefix);
            });
        }

        return $query
            ->orderBy('name_en')
            ->limit($limit)
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'label' => $category->localizedName(),
                'meta' => null,
            ])
            ->values();
    }

    private function customers(string $term, int $limit): Collection
    {
        $query = Customer::query()
            ->where('is_active', true)
            ->select(['id', 'name', 'phone']);

        if ($term !== '') {
            $prefix = $this->prefix($term);
            $query->where(function (Builder $builder) use ($prefix): void {
                $builder->where('name', 'like', $prefix)
                    ->orWhere('phone', 'like', $prefix)
                    ->orWhere('alternate_phone', 'like', $prefix);
            });
        }

        return $query
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'label' => $customer->name,
                'meta' => $customer->phone,
            ])
            ->values();
    }

    private function suppliers(string $term, int $limit): Collection
    {
        $query = Supplier::query()
            ->where('is_active', true)
            ->select(['id', 'name', 'phone']);

        if ($term !== '') {
            $prefix = $this->prefix($term);
            $query->where(function (Builder $builder) use ($prefix): void {
                $builder->where('name', 'like', $prefix)
                    ->orWhere('phone', 'like', $prefix)
                    ->orWhere('alternate_phone', 'like', $prefix);
            });
        }

        return $query
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Supplier $supplier): array => [
                'id' => $supplier->id,
                'label' => $supplier->name,
                'meta' => $supplier->phone,
            ])
            ->values();
    }

    private function applyProductSearch(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        $prefix = $this->prefix($term);

        $query->where(function (Builder $builder) use ($term, $prefix): void {
            $builder->where('sku', $term)
                ->orWhere('sku', 'like', $prefix)
                ->orWhere('name_en', 'like', $prefix)
                ->orWhere('name_fa', 'like', $prefix)
                ->orWhere('name_ps', 'like', $prefix);
        });
    }

    private function selectedProduct(mixed $id): ?array
    {
        if (! $id) {
            return null;
        }

        $product = Product::query()->find($id, ['id', 'sku', 'name_en', 'name_fa', 'name_ps']);

        return $product ? [
            'id' => $product->id,
            'label' => $product->localizedName(),
            'meta' => $product->sku,
        ] : null;
    }

    private function selectedCategory(mixed $id): ?array
    {
        if (! $id) {
            return null;
        }

        $category = Category::query()->find($id, ['id', 'name_en', 'name_fa', 'name_ps']);

        return $category ? [
            'id' => $category->id,
            'label' => $category->localizedName(),
            'meta' => null,
        ] : null;
    }

    private function selectedCustomer(mixed $id): ?array
    {
        if (! $id) {
            return null;
        }

        $customer = Customer::query()->find($id, ['id', 'name', 'phone']);

        return $customer ? [
            'id' => $customer->id,
            'label' => $customer->name,
            'meta' => $customer->phone,
        ] : null;
    }

    private function selectedSupplier(mixed $id): ?array
    {
        if (! $id) {
            return null;
        }

        $supplier = Supplier::query()->find($id, ['id', 'name', 'phone']);

        return $supplier ? [
            'id' => $supplier->id,
            'label' => $supplier->name,
            'meta' => $supplier->phone,
        ] : null;
    }

    private function prefix(string $term): string
    {
        return addcslashes($term, '%_').'%' ;
    }
}
