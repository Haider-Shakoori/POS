<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryWriteoffRequest;
use App\Services\Inventory\InventoryWriteoffService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class InventoryWriteoffController extends Controller
{
    public function store(
        StoreInventoryWriteoffRequest $request,
        InventoryWriteoffService $writeoffs,
    ): RedirectResponse {
        try {
            $writeoff = $writeoffs->post($request->validated(), $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['inventory_writeoff' => $exception->getMessage()]);
        }

        return redirect()
            ->route('inventory.operations.index')
            ->with('status', __('ui.inventory_writeoff_posted', ['number' => $writeoff->number]));
    }
}
