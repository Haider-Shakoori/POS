<?php

namespace App\Http\Controllers\Closing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Closing\CloseShiftRequest;
use App\Http\Requests\Closing\ReopenShiftRequest;
use App\Models\CashierShift;
use App\Services\Closing\ShiftClosingService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class ShiftClosingController extends Controller
{
    public function close(
        CloseShiftRequest $request,
        CashierShift $cashierShift,
        ShiftClosingService $closing,
    ): RedirectResponse {
        try {
            $closing->close($cashierShift, $request->validated(), $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['shift_close' => $exception->getMessage()]);
        }

        return redirect()
            ->route('cash.index')
            ->with('status', __('ui.shift_closed'));
    }

    public function reopen(
        ReopenShiftRequest $request,
        CashierShift $cashierShift,
        ShiftClosingService $closing,
    ): RedirectResponse {
        try {
            $closing->reopen(
                $cashierShift,
                $request->string('reason')->toString(),
                $request->user(),
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['shift_reopen' => $exception->getMessage()]);
        }

        return back()->with('status', __('ui.shift_reopened'));
    }
}
