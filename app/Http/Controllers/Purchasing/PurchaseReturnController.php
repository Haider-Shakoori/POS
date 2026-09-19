<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StorePurchaseReturnRequest;
use App\Models\GoodsReceipt;
use App\Services\Purchasing\PurchaseReturnService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class PurchaseReturnController extends Controller
{
    public function store(
        StorePurchaseReturnRequest $request,
        GoodsReceipt $goodsReceipt,
        PurchaseReturnService $returns,
    ): RedirectResponse {
        try {
            $purchaseReturn = $returns->post(
                $goodsReceipt,
                $request->validated(),
                $request->user(),
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['purchase_return' => $exception->getMessage()]);
        }

        return redirect()
            ->route('purchasing.receipts.show', $goodsReceipt)
            ->with('status', __('ui.purchase_return_posted', ['number' => $purchaseReturn->number]));
    }
}
