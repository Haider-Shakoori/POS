<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\PostGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Purchasing\GoodsReceiptService;
use App\Support\Decimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function index(Request $request): View
    {
        $receipts = GoodsReceipt::query()
            ->with(['supplier', 'purchaseOrder'])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = trim((string) $request->input('q'));
                $query->where(function ($query) use ($term): void {
                    $query->where('number', 'like', "%{$term}%")
                        ->orWhere('supplier_invoice_reference', 'like', "%{$term}%")
                        ->orWhereHas('supplier', fn ($query) => $query->where('name', 'like', "%{$term}%"));
                });
            })
            ->latest('received_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('purchasing.receipts.index', compact('receipts'));
    }

    public function createForOrder(PurchaseOrder $purchaseOrder): View
    {
        abort_unless(auth()->user()->hasPermission('purchases.receive'), 403);

        $purchaseOrder->load([
            'supplier',
            'items.product',
            'items.productUnit.unit',
        ]);

        abort_unless($purchaseOrder->isReceivable(), 404);

        return view('purchasing.receipts.create', [
            'order' => $purchaseOrder,
            'supplier' => $purchaseOrder->supplier,
            'suppliers' => collect([$purchaseOrder->supplier]),
            'productUnits' => collect(),
        ]);
    }

    public function createDirect(): View
    {
        abort_unless(auth()->user()->hasPermission('purchases.direct_receive'), 403);

        return view('purchasing.receipts.create', [
            'order' => null,
            'supplier' => null,
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
        PostGoodsReceiptRequest $request,
        GoodsReceiptService $service,
    ): RedirectResponse {
        $receipt = $service->post($request->validated(), $request->user());

        return redirect()
            ->route('purchasing.receipts.show', $receipt)
            ->with('status', __('ui.goods_receipt_posted'));
    }

    public function show(GoodsReceipt $goodsReceipt): View
    {
        $goodsReceipt->load([
            'supplier',
            'purchaseOrder',
            'createdBy',
            'postedBy',
            'items.product',
            'items.productUnit.unit',
            'items.stockMovement',
            'expenses',
            'payments',
        ]);

        return view('purchasing.receipts.show', ['receipt' => $goodsReceipt]);
    }
}
