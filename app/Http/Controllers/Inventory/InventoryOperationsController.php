<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryWriteoff;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockCount;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class InventoryOperationsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $expiryDays = min(max($request->integer('expiry_days', 30), 1), 365);
        $today = today();
        $expiryEnd = $today->copy()->addDays($expiryDays);

        $products = Product::query()
            ->with(['baseUnit', 'batches'])
            ->where('is_active', true)
            ->where('track_stock', true)
            ->orderBy('name_en')
            ->get();

        $countTargets = collect();
        $damageTargets = collect();

        foreach ($products as $product) {
            if ($product->track_expiry) {
                foreach ($product->batches->sortBy('expires_at') as $batch) {
                    $target = [
                        'value' => $product->id.':'.$batch->id,
                        'label' => $product->localizedName().' · '.$batch->batch_number,
                        'stock' => Decimal::normalize($batch->stock_on_hand),
                        'unit' => $product->baseUnit?->symbol,
                        'expires_at' => $batch->expires_at?->format('Y-m-d'),
                    ];

                    $countTargets->push($target);

                    if (Decimal::isPositive($batch->stock_on_hand)) {
                        $damageTargets->push($target);
                    }
                }

                continue;
            }

            $target = [
                'value' => $product->id.':',
                'label' => $product->localizedName(),
                'stock' => Decimal::normalize($product->stock_on_hand),
                'unit' => $product->baseUnit?->symbol,
                'expires_at' => null,
            ];

            $countTargets->push($target);

            if (Decimal::isPositive($product->stock_on_hand)) {
                $damageTargets->push($target);
            }
        }

        $expiredBatches = ProductBatch::query()
            ->with(['product.baseUnit'])
            ->where('stock_on_hand', '>', 0)
            ->whereDate('expires_at', '<', $today)
            ->orderBy('expires_at')
            ->get();

        $expiringBatches = ProductBatch::query()
            ->with(['product.baseUnit'])
            ->where('stock_on_hand', '>', 0)
            ->whereDate('expires_at', '>=', $today)
            ->whereDate('expires_at', '<=', $expiryEnd)
            ->orderBy('expires_at')
            ->get();

        return view('inventory.operations.index', [
            'expiryDays' => $expiryDays,
            'countTargets' => $countTargets,
            'damageTargets' => $damageTargets,
            'expiryTargets' => $expiredBatches->map(fn (ProductBatch $batch) => [
                'value' => $batch->product_id.':'.$batch->id,
                'label' => $batch->product->localizedName().' · '.$batch->batch_number,
                'stock' => Decimal::normalize($batch->stock_on_hand),
                'unit' => $batch->product->baseUnit?->symbol,
                'expires_at' => $batch->expires_at?->format('Y-m-d'),
            ])->values(),
            'expiredBatches' => $expiredBatches,
            'expiringBatches' => $expiringBatches,
            'reorderSuggestions' => $this->reorderSuggestions($products),
            'recentCounts' => StockCount::query()
                ->with(['countedBy', 'approvedBy'])
                ->withCount('items')
                ->latest('counted_at')
                ->limit(20)
                ->get(),
            'recentWriteoffs' => InventoryWriteoff::query()
                ->with('postedBy')
                ->withCount('items')
                ->latest('posted_at')
                ->limit(20)
                ->get(),
        ]);
    }

    private function reorderSuggestions(Collection $products): Collection
    {
        return $products
            ->filter(function (Product $product): bool {
                if (! $product->track_stock) {
                    return false;
                }

                $thresholdConfigured = Decimal::isPositive($product->minimum_stock)
                    || Decimal::isPositive($product->reorder_quantity);

                return $thresholdConfigured
                    && Decimal::compare($product->stock_on_hand, $product->minimum_stock) <= 0;
            })
            ->map(function (Product $product): array {
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
            })
            ->sortBy(fn (array $row) => [
                $row['status'] === 'out' ? 0 : 1,
                $row['product']->localizedName(),
            ])
            ->values();
    }
}
