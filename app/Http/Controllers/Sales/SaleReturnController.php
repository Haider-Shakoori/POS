<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\ReturnSaleRequest;
use App\Http\Requests\Sales\VoidSaleRequest;
use App\Models\Sale;
use App\Services\Sales\SaleReturnService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class SaleReturnController extends Controller
{
    public function store(
        ReturnSaleRequest $request,
        Sale $sale,
        SaleReturnService $returns,
    ): RedirectResponse {
        $data = $request->validated();
        $data['refunds'] = $this->refundsFromRequest($data);

        try {
            $return = $returns->returnItems($sale, $data, $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['return' => $exception->getMessage()]);
        }

        return redirect()
            ->route('sales.show', $sale)
            ->with('status', __('ui.sale_return_posted', ['number' => $return->number]));
    }

    public function void(
        VoidSaleRequest $request,
        Sale $sale,
        SaleReturnService $returns,
    ): RedirectResponse {
        $data = $request->validated();
        $data['refunds'] = $this->refundsFromRequest($data);

        try {
            $return = $returns->void($sale, $data, $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['void' => $exception->getMessage()]);
        }

        return redirect()
            ->route('sales.show', $sale)
            ->with('status', __('ui.sale_void_posted', ['number' => $return->number]));
    }

    private function refundsFromRequest(array $data): array
    {
        if (empty($data['refund_payment_method_id'])) {
            return [];
        }

        return [[
            'payment_method_id' => $data['refund_payment_method_id'],
            'amount' => null,
            'reference' => $data['refund_reference'] ?? null,
        ]];
    }
}
