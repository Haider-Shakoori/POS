<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CompleteSaleRequest;
use App\Models\Sale;
use App\Services\Sales\SaleService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function store(
        CompleteSaleRequest $request,
        SaleService $sales,
    ): JsonResponse {
        try {
            $sale = $sales->complete($request->validated(), $request->user());
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => __('ui.sale_completed'),
            'sale' => [
                'id' => $sale->id,
                'number' => $sale->number,
                'net_total' => $sale->net_total,
                'balance_due' => $sale->balance_due,
                'url' => route('sales.show', $sale),
            ],
        ], 201);
    }

    public function show(Sale $sale): View
    {
        $sale->load([
            'cashier',
            'terminal',
            'items.product',
            'items.productUnit.unit',
            'items.stockAllocations.batch',
            'items.costConsumptions.layer',
        ]);

        return view('sales.show', compact('sale'));
    }
}
