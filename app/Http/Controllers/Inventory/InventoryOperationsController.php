<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryWriteoff;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockCount;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryOperationsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $expiryDays = min(max($request->integer('expiry_days', 30), 1), 365);
        $today = today();
        $expiryEnd = $today->copy()->addDays($expiryDays);

        $expiredQuery = ProductBatch::query()
            ->where('stock_on_hand', '>', 0)
            ->whereDate('expires_at', '<', $today);

        $expiringQuery = ProductBatch::query()
            ->where('stock_on_hand', '>', 0)
            ->whereDate('expires_at', '>=', $today)
            ->whereDate('expires_at', '<=', $expiryEnd);

        $reorderQuery = Product::query()
            ->where('is_active', true)
            ->where('track_stock', true)
            ->where(function ($query): void {
                $query->where('minimum_stock', '>', 0)
                    ->orWhere('reorder_quantity', '>', 0);
            })
            ->whereColumn('stock_on_hand', '<=', 'minimum_stock');

        $expiredBatchCount = (clone $expiredQuery)->count();
        $expiringBatchCount = (clone $expiringQuery)->count();
        $reorderSuggestionCount = (clone $reorderQuery)->count();
        $pendingStockCount = StockCount::query()->where('status', 'draft')->count();

        $expiredBatches = (clone $expiredQuery)
            ->with(['product.baseUnit:id,symbol'])
            ->orderBy('expires_at')
            ->orderBy('id')
            ->paginate(12, ['*'], 'expired_page')
            ->withQueryString();

        $expiringBatches = (clone $expiringQuery)
            ->with(['product.baseUnit:id,symbol'])
            ->orderBy('expires_at')
            ->orderBy('id')
            ->paginate(12, ['*'], 'expiring_page')
            ->withQueryString();

        $reorderSuggestions = (clone $reorderQuery)
            ->with('baseUnit:id,symbol')
            ->orderByRaw('CASE WHEN stock_on_hand <= 0 THEN 0 ELSE 1 END')
            ->orderBy('name_en')
            ->orderBy('id')
            ->paginate(20, ['*'], 'reorder_page')
            ->withQueryString()
            ->through(fn (Product $product): array => $this->reorderSuggestion($product));

        return view('inventory.operations.index', [
            'expiryDays' => $expiryDays,
            'expiredBatchCount' => $expiredBatchCount,
            'expiringBatchCount' => $expiringBatchCount,
            'reorderSuggestionCount' => $reorderSuggestionCount,
            'pendingStockCount' => $pendingStockCount,
            'expiredBatches' => $expiredBatches,
            'expiringBatches' => $expiringBatches,
            'reorderSuggestions' => $reorderSuggestions,
            'targetSearchUrl' => route('inventory.operations.targets.search'),
            'recentCounts' => StockCount::query()
                ->with(['countedBy:id,name', 'approvedBy:id,name'])
                ->withCount('items')
                ->latest('counted_at')
                ->limit(20)
                ->get(),
            'recentWriteoffs' => InventoryWriteoff::query()
                ->with('postedBy:id,name')
                ->withCount('items')
                ->latest('posted_at')
                ->limit(20)
                ->get(),
        ]);
    }

    private function reorderSuggestion(Product $product): array
    {
        $deficit = Decimal::subtract($product->minimum_stock, $product->stock_on_hand);

        if (Decimal::isNegative($deficit)) {
            $deficit = '0.000000';
        }

        $suggested = Decimal::compare($product->reorder_quantity, $deficit) >= 0
            ? Decimal::normalize($product->reorder_quantity)
            : Decimal::normalize($deficit);

        return [
            'product' => $product,
            'status' => Decimal::compare($product->stock_on_hand, '0') <= 0 ? 'out' : 'low',
            'suggested_quantity' => $suggested,
        ];
    }
}
