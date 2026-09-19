<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CompleteSaleRequest;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Services\Sales\CheckoutService;
use App\Support\Decimal;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q'));

        $sales = Sale::query()
            ->with(['cashier', 'customer'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('number', 'like', "%{$search}%")
                        ->orWhere('customer_name_snapshot', 'like', "%{$search}%");
                });
            })
            ->latest('sold_at')
            ->paginate(30)
            ->withQueryString();

        return view('sales.index', compact('sales', 'search'));
    }

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
            'returns.items.saleItem',
            'returns.refunds.paymentMethod',
            'items.product',
            'items.productUnit.unit',
            'items.stockAllocations.batch',
            'items.costConsumptions.layer',
            'items.returnItems',
        ]);

        $cogsReversedTotal = '0.00';

        foreach ($sale->returns as $return) {
            $cogsReversedTotal = Decimal::add($cogsReversedTotal, $return->cogs_reversed, 2);
        }

        return view('sales.show', [
            'sale' => $sale,
            'cogsReversedTotal' => $cogsReversedTotal,
            'paymentMethods' => PaymentMethod::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
        ]);
    }
}
