<?php

namespace App\Http\Controllers\Sales;

use App\Enums\ShiftStatus;
use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\PaymentMethod;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();

        $hasOpenShift = CashierShift::query()
            ->where('user_id', $user->id)
            ->where('status', ShiftStatus::Open->value)
            ->exists();

        return view('pos.index', [
            'hasOpenShift' => $hasOpenShift,
            'canDiscount' => $user->hasPermission('sales.discount'),
            'canOverrideMinimum' => $user->hasPermission('sales.override_min_price'),
            'canCredit' => $user->hasPermission('sales.credit'),
            'canHold' => $user->hasPermission('sales.hold'),
            'canQuickCreateCustomers' => $user->hasPermission('customers.quick_create') || $user->hasPermission('customers.manage'),
            'paymentMethods' => PaymentMethod::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (PaymentMethod $method) => [
                    'id' => $method->id,
                    'code' => $method->code,
                    'name' => $method->localizedName(),
                    'is_cash' => $method->is_cash,
                ])
                ->values(),
        ]);
    }
}
