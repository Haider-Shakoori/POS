<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreOpeningStockRequest;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;

class OpeningStockController extends Controller
{
    public function store(
        StoreOpeningStockRequest $request,
        Product $product,
        InventoryService $inventory,
    ): RedirectResponse {
        $inventory->addOpeningStock(
            product: $product,
            sourceQuantity: $request->string('quantity')->toString(),
            sourceUnitId: $request->integer('unit_id'),
            batchData: $request->filled('batch_number') ? [
                'batch_number' => $request->string('batch_number')->toString(),
                'manufactured_at' => $request->input('manufactured_at'),
                'expires_at' => $request->input('expires_at'),
                'notes' => $request->input('notes'),
            ] : null,
            unitCost: $request->filled('unit_cost') ? $request->string('unit_cost')->toString() : null,
            actor: $request->user(),
            notes: $request->input('notes'),
            idempotencyKey: $request->input('idempotency_key'),
        );

        return back()->with('status', __('ui.opening_stock_recorded'));
    }
}
