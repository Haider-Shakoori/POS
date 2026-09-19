<?php

namespace App\Http\Controllers\Cash;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cash\OpenShiftRequest;
use App\Services\Cash\ShiftOpeningService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class ShiftOpeningController extends Controller
{
    public function store(
        OpenShiftRequest $request,
        ShiftOpeningService $shifts,
    ): RedirectResponse {
        try {
            $shifts->open($request->validated(), $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['shift' => $exception->getMessage()]);
        }

        return redirect()
            ->route('cash.index')
            ->with('status', __('ui.shift_opened'));
    }
}
