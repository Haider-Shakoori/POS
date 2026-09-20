<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $user = $request->user();
        $like = '%'.$term.'%';
        $limit = 6;
        $results = new Collection;

        if ($user->hasPermission('inventory.view')) {
            Product::query()
                ->where('is_active', true)
                ->where(function ($query) use ($like): void {
                    $query->where('sku', 'like', $like)
                        ->orWhere('name_en', 'like', $like)
                        ->orWhere('name_fa', 'like', $like)
                        ->orWhere('name_ps', 'like', $like);
                })
                ->orderBy('name_en')
                ->limit($limit)
                ->get()
                ->each(function (Product $product) use ($results): void {
                    $results->push([
                        'type' => 'product',
                        'label' => $product->localizedName(),
                        'meta' => $product->sku,
                        'url' => route('inventory.products.show', $product),
                    ]);
                });
        }

        if ($user->hasPermission('customers.view')) {
            Customer::query()
                ->where(function ($query) use ($like): void {
                    $query->where('name', 'like', $like)->orWhere('phone', 'like', $like);
                })
                ->orderBy('name')
                ->limit($limit)
                ->get()
                ->each(function (Customer $customer) use ($results): void {
                    $results->push([
                        'type' => 'customer',
                        'label' => $customer->name,
                        'meta' => $customer->phone,
                        'url' => route('customers.show', $customer),
                    ]);
                });
        }

        if ($user->hasPermission('suppliers.view')) {
            Supplier::query()
                ->where(function ($query) use ($like): void {
                    $query->where('name', 'like', $like)->orWhere('phone', 'like', $like);
                })
                ->orderBy('name')
                ->limit($limit)
                ->get()
                ->each(function (Supplier $supplier) use ($results): void {
                    $results->push([
                        'type' => 'supplier',
                        'label' => $supplier->name,
                        'meta' => $supplier->phone,
                        'url' => route('purchasing.suppliers.show', $supplier),
                    ]);
                });
        }

        if ($user->hasPermission('sales.view')) {
            Sale::query()
                ->where('number', 'like', $like)
                ->latest('id')
                ->limit($limit)
                ->get()
                ->each(function (Sale $sale) use ($results): void {
                    $results->push([
                        'type' => 'sale',
                        'label' => $sale->number,
                        'meta' => $sale->customer_name_snapshot,
                        'url' => route('sales.show', $sale),
                    ]);
                });
        }

        return response()->json(['data' => $results->take(12)->values()]);
    }
}
