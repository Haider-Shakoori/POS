<?php

namespace App\Http\Controllers\Cash;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cash\StoreManualCashMovementRequest;
use App\Models\CashierShift;
use App\Services\Cash\ManualCashMovementService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class ManualCashMovementController extends Controller
{
    public function store(
        StoreManualCashMovementRequest $request,
        CashierShift $cashierShift,
        ManualCashMovementService $movements,
    ): RedirectResponse {
        try {
            $movements->record($cashierShift, $request->validated(), $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['cash_movement' => $exception->getMessage()]);
        }

        return redirect()
            ->route('cash.index')
            ->with('status', __('ui.cash_movement_recorded'));
    }
}
