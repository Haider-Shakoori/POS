<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\ShopSetting;
use App\Support\Money;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'shop' => ShopSetting::query()->first(),
            'openShift' => CashierShift::query()
                ->where('user_id', auth()->id())
                ->where('status', 'open')
                ->latest('opened_at')
                ->first(),
            'currencyCode' => Money::code(),
        ]);
    }
}
