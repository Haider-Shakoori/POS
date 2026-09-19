<?php

namespace App\Http\Controllers\Cash;

use App\Enums\ShiftStatus;
use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\ExpenseCategory;
use App\Models\OperatingEntry;
use App\Models\PaymentMethod;
use App\Models\Terminal;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashDrawerController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $shift = CashierShift::query()
            ->with('terminal')
            ->where('user_id', $user->id)
            ->where('status', ShiftStatus::Open->value)
            ->latest('opened_at')
            ->first();

        if ($shift) {
            $shift->load([
                'cashMovements' => fn ($query) => $query
                    ->latest('occurred_at')
                    ->latest('id')
                    ->limit(100),
            ]);
        }

        return view('cash.index', [
            'shift' => $shift,
            'terminals' => Terminal::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'expenseCategories' => ExpenseCategory::query()
                ->where('entry_type', 'expense')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'incomeCategories' => ExpenseCategory::query()
                ->where('entry_type', 'income')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'paymentMethods' => PaymentMethod::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'recentEntries' => OperatingEntry::query()
                ->with(['category', 'paymentMethod'])
                ->latest('occurred_at')
                ->limit(50)
                ->get(),
        ]);
    }
}
