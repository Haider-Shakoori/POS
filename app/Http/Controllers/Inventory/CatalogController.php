<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreBrandRequest;
use App\Http\Requests\Inventory\StoreCategoryRequest;
use App\Http\Requests\Inventory\StoreUnitRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(): View
    {
        return view('inventory.catalog.index', [
            'categories' => Category::query()->with('parent')->orderBy('sort_order')->orderBy('name_en')->get(),
            'brands' => Brand::query()->orderBy('name_en')->get(),
            'units' => Unit::query()->orderBy('name_en')->get(),
        ]);
    }

    public function storeCategory(StoreCategoryRequest $request, AuditLogger $audit): RedirectResponse
    {
        $category = Category::create([
            ...$request->validated(),
            'sort_order' => $request->integer('sort_order', 0),
            'is_active' => true,
        ]);

        $audit->record('inventory.category.created', $category, newValues: $category->only(['parent_id', 'name_en']), actor: $request->user());

        return back()->with('status', __('ui.category_created'));
    }

    public function storeBrand(StoreBrandRequest $request, AuditLogger $audit): RedirectResponse
    {
        $brand = Brand::create([...$request->validated(), 'is_active' => true]);

        $audit->record('inventory.brand.created', $brand, newValues: $brand->only(['name_en']), actor: $request->user());

        return back()->with('status', __('ui.brand_created'));
    }

    public function storeUnit(StoreUnitRequest $request, AuditLogger $audit): RedirectResponse
    {
        $unit = Unit::create([...$request->validated(), 'is_active' => true]);

        $audit->record('inventory.unit.created', $unit, newValues: $unit->only(['code', 'name_en', 'decimal_places']), actor: $request->user());

        return back()->with('status', __('ui.unit_created'));
    }
}
