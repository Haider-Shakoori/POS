<?php

namespace App\Http\Controllers;

use App\Enums\ShiftStatus;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Support\Decimal;
use App\Support\Money;
use App\Support\ShopSettingsStore;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ShopSettingsStore $settings): View
    {
        $user = auth()->user();
        $user->loadMissing('roles.permissions');

        $openShift = CashierShift::query()
            ->with('terminal:id,code,name')
            ->where('user_id', $user->id)
            ->where('status', ShiftStatus::Open->value)
            ->latest('opened_at')
            ->first();

        $start = now()->startOfDay();
        $end = now()->endOfDay();

        $canViewSales = $user->hasPermission('sales.view');
        $canViewCustomers = $user->hasPermission('customers.view');
        $canViewSuppliers = $user->hasPermission('suppliers.view');
        $canViewInventory = $user->hasPermission('inventory.view');

        $todayNetSales = null;
        $todayTransactions = null;
        $recentSales = collect();

        if ($canViewSales) {
            $salesTotal = (string) Sale::query()
                ->whereBetween('sold_at', [$start, $end])
                ->sum('net_total');

            $returnTotal = (string) SaleReturn::query()
                ->whereBetween('posted_at', [$start, $end])
                ->sum('return_total');

            $todayNetSales = Decimal::subtract($salesTotal, $returnTotal, 2);
            $todayTransactions = Sale::query()
                ->whereBetween('sold_at', [$start, $end])
                ->count();

            $recentSales = Sale::query()
                ->with('customer:id,name')
                ->latest('sold_at')
                ->latest('id')
                ->limit(6)
                ->get([
                    'id',
                    'number',
                    'customer_id',
                    'customer_name_snapshot',
                    'net_total',
                    'returned_total',
                    'payment_status',
                    'sold_at',
                ]);
        }

        $lowStockProducts = collect();
        $lowStockCount = null;

        if ($canViewInventory) {
            $lowStock = Product::query()
                ->where('is_active', true)
                ->where('track_stock', true)
                ->whereColumn('stock_on_hand', '<=', 'minimum_stock');

            $lowStockCount = (clone $lowStock)->count();
            $lowStockProducts = $lowStock
                ->with('baseUnit:id,symbol')
                ->orderBy('stock_on_hand')
                ->limit(6)
                ->get([
                    'id',
                    'base_unit_id',
                    'sku',
                    'name_en',
                    'name_fa',
                    'name_ps',
                    'stock_on_hand',
                    'minimum_stock',
                ]);
        }

        return view('dashboard', [
            'shop' => $settings->get(),
            'openShift' => $openShift,
            'currencyCode' => Money::code(),
            'todayNetSales' => $todayNetSales,
            'todayTransactions' => $todayTransactions,
            'receivables' => $canViewCustomers
                ? (string) Customer::query()->where('current_balance', '>', 0)->sum('current_balance')
                : null,
            'payables' => $canViewSuppliers
                ? (string) Supplier::query()->where('current_balance', '>', 0)->sum('current_balance')
                : null,
            'lowStockCount' => $lowStockCount,
            'lowStockProducts' => $lowStockProducts,
            'recentSales' => $recentSales,
            'canViewSales' => $canViewSales,
            'canViewCustomers' => $canViewCustomers,
            'canViewSuppliers' => $canViewSuppliers,
            'canViewInventory' => $canViewInventory,
        ]);
    }
}
