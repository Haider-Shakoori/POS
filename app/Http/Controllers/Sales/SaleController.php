<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CompleteSaleRequest;
use App\Models\Sale;
use App\Services\Sales\CheckoutService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function store(
        CompleteSaleRequest $request,
        CheckoutService $checkout,
    ): JsonResponse {
        try {
            $sale = $checkout->checkout($request->validated(), $request->user());
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
                'paid_amount' => $sale->paid_amount,
                'balance_due' => $sale->balance_due,
                'payment_status' => $sale->payment_status->value,
                'customer_name' => $sale->customer_name_snapshot,
                'url' => route('sales.show', $sale),
            ],
        ], 201);
    }

    public function show(Sale $sale): View
    {
        $sale->load([
            'cashier',
            'terminal',
            'customer',
            'payments.paymentMethod',
            'items.product',
            'items.productUnit.unit',
            'items.stockAllocations.batch',
            'items.costConsumptions.layer',
        ]);

        return view('sales.show', compact('sale'));
    }
}
