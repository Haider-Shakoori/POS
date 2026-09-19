<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ApproveStockCountRequest;
use App\Http\Requests\Inventory\StoreStockCountRequest;
use App\Models\StockCount;
use App\Services\Inventory\StockCountService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class StockCountController extends Controller
{
    public function store(
        StoreStockCountRequest $request,
        StockCountService $counts,
    ): RedirectResponse {
        try {
            $count = $counts->createDraft($request->validated(), $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['stock_count' => $exception->getMessage()]);
        }

        return redirect()
            ->route('inventory.operations.index')
            ->with('status', __('ui.stock_count_created', ['number' => $count->number]));
    }

    public function approve(
        ApproveStockCountRequest $request,
        StockCount $stockCount,
        StockCountService $counts,
    ): RedirectResponse {
        try {
            $counts->approve($stockCount, $request->validated(), $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['stock_count_approval' => $exception->getMessage()]);
        }

        return redirect()
            ->route('inventory.operations.index')
            ->with('status', __('ui.stock_count_approved'));
    }
}
