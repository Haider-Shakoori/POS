<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\Catalog\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()
            ->with(['category', 'brand', 'baseUnit'])
            ->withCount('barcodes')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = trim((string) $request->input('q'));

                $query->where(function ($query) use ($term): void {
                    $query->where('sku', 'like', "%{$term}%")
                        ->orWhere('name_en', 'like', "%{$term}%")
                        ->orWhere('name_fa', 'like', "%{$term}%")
                        ->orWhere('name_ps', 'like', "%{$term}%")
                        ->orWhereHas('barcodes', fn ($query) => $query->where('barcode', 'like', "%{$term}%"));
                });
            })
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->input('status') === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name_en')
            ->paginate(20)
            ->withQueryString();

        return view('inventory.products.index', [
            'products' => $products,
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name_en')->get(),
        ]);
    }

    public function create(): View
    {
        return view('inventory.products.create', [
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name_en')->get(),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name_en')->get(),
            'units' => Unit::query()->where('is_active', true)->orderBy('name_en')->get(),
        ]);
    }

    public function store(StoreProductRequest $request, ProductService $service): RedirectResponse
    {
        $product = $service->create($request->validated(), $request->user());

        return redirect()
            ->route('inventory.products.show', $product)
            ->with('status', __('ui.product_created'));
    }

    public function show(Product $product): View
    {
        $product->load([
            'category',
            'brand',
            'baseUnit',
            'productUnits.unit',
            'barcodes.productUnit.unit',
            'batches' => fn ($query) => $query->orderBy('expires_at'),
        ]);

        return view('inventory.products.show', [
            'product' => $product,
            'movements' => $product->stockMovements()
                ->with(['batch', 'sourceUnit', 'actor'])
                ->latest('occurred_at')
                ->limit(25)
                ->get(),
        ]);
    }
}
