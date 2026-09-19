<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\HoldSaleRequest;
use App\Models\HeldSale;
use App\Services\Sales\HeldSaleService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeldSaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = HeldSale::query()
            ->with(['customer', 'items.productUnit.product', 'items.productUnit.unit'])
            ->where('status', 'held')
            ->latest('held_at');

        if (! $user->hasPermission('sales.void')) {
            $query->where('cashier_user_id', $user->id);
        }

        return response()->json([
            'data' => $query->limit(50)->get()->map(fn (HeldSale $held) => $this->payload($held)),
        ]);
    }

    public function store(HoldSaleRequest $request, HeldSaleService $heldSales): JsonResponse
    {
        try {
            $held = $heldSales->hold($request->validated(), $request->user());
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => __('ui.sale_held'),
            'held_sale' => $this->payload($held),
        ], 201);
    }

    public function resume(Request $request, HeldSale $heldSale, HeldSaleService $heldSales): JsonResponse
    {
        try {
            $held = $heldSales->resume($heldSale, $request->user());
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => __('ui.held_sale_resumed'),
            'held_sale' => $this->payload($held),
        ]);
    }

    public function release(Request $request, HeldSale $heldSale, HeldSaleService $heldSales): JsonResponse
    {
        try {
            $heldSales->release($heldSale, $request->user());
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => __('ui.held_sale_released')]);
    }

    private function payload(HeldSale $held): array
    {
        $held->loadMissing(['customer', 'items.productUnit.product', 'items.productUnit.unit']);

        return [
            'id' => $held->id,
            'number' => $held->number,
            'customer' => $held->customer ? [
                'id' => $held->customer->id,
                'name' => $held->customer->name,
                'phone' => $held->customer->phone,
                'credit_limit' => $held->customer->credit_limit,
                'current_balance' => $held->customer->current_balance,
            ] : null,
            'customer_name' => $held->customer_name_snapshot,
            'sale_discount_amount' => $held->sale_discount_amount,
            'notes' => $held->notes,
            'held_at' => $held->held_at?->toIso8601String(),
            'items' => $held->items->map(fn ($item) => [
                'product_unit_id' => $item->product_unit_id,
                'product_id' => $item->productUnit->product_id,
                'name' => $item->product_name_snapshot,
                'sku' => $item->sku_snapshot,
                'unit' => $item->unit_name_snapshot,
                'decimal_places' => $item->productUnit->unit->decimal_places,
                'conversion_factor' => $item->productUnit->conversion_factor,
                'price' => $item->unit_price_snapshot,
                'quantity' => $item->quantity,
                'line_discount_amount' => $item->line_discount_amount,
                'track_stock' => $item->productUnit->product->track_stock,
                'track_expiry' => $item->productUnit->product->track_expiry,
                'available_quantity' => $item->productUnit->product->track_stock
                    ? \App\Support\Decimal::divide(
                        $item->productUnit->product->stock_on_hand,
                        $item->productUnit->conversion_factor
                    )
                    : null,
            ])->values(),
        ];
    }
}
