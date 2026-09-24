<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\ShopSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleReceiptController extends Controller
{
    public function __invoke(Request $request, Sale $sale): View
    {
        $sale->load([
            'cashier',
            'terminal',
            'customer',
            'items.productUnit.unit',
            'payments.paymentMethod',
            'returns',
        ]);

        $shop = ShopSetting::query()->firstOrCreate(['id' => 1]);
        app()->setLocale($shop->receipt_locale ?: 'en');

        return view('sales.receipt', [
            'sale' => $sale,
            'shop' => $shop,
            'autoprint' => $request->boolean('autoprint'),
            'embedded' => $request->boolean('embed'),
        ]);
    }
}
