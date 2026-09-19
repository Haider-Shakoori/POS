<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StorePurchaseOrderRequest;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = PurchaseOrder::query()
            ->with('supplier')
            ->withCount('items')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = trim((string) $request->input('q'));
                $query->where(function ($query) use ($term): void {
                    $query->where('number', 'like', "%{$term}%")
                        ->orWhere('supplier_reference', 'like', "%{$term}%")
                        ->orWhereHas('supplier', fn ($query) => $query->where('name', 'like', "%{$term}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest('order_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('purchasing.orders.index', compact('orders'));
    }

    public function create(): View
    {
        return view('purchasing.orders.create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'productUnits' => ProductUnit::query()
                ->with(['product', 'unit'])
                ->where('can_purchase', true)
                ->whereHas('product', fn ($query) => $query->where('is_active', true))
                ->orderBy('product_id')
                ->get(),
        ]);
    }

    public function store(
        StorePurchaseOrderRequest $request,
        PurchaseOrderService $service,
    ): RedirectResponse {
        $order = $service->create($request->validated(), $request->user());

        return redirect()
            ->route('purchasing.orders.show', $order)
            ->with('status', __('ui.purchase_order_created'));
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load([
            'supplier',
            'createdBy',
            'approvedBy',
            'items.product',
            'items.productUnit.unit',
            'goodsReceipts' => fn ($query) => $query->latest('received_at'),
        ]);

        return view('purchasing.orders.show', ['order' => $purchaseOrder]);
    }

    public function approve(
        PurchaseOrder $purchaseOrder,
        Request $request,
        PurchaseOrderService $service,
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('purchases.approve'), 403);
        $service->approve($purchaseOrder, $request->user());

        return back()->with('status', __('ui.purchase_order_approved'));
    }

    public function cancel(
        PurchaseOrder $purchaseOrder,
        Request $request,
        PurchaseOrderService $service,
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('purchases.approve'), 403);
        $service->cancel($purchaseOrder, $request->user());

        return back()->with('status', __('ui.purchase_order_cancelled'));
    }
}
