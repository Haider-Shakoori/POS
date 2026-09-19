<?php

namespace App\Http\Controllers\Cash;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cash\StoreOperatingEntryRequest;
use App\Services\Cash\OperatingEntryService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class OperatingEntryController extends Controller
{
    public function store(
        StoreOperatingEntryRequest $request,
        OperatingEntryService $entries,
    ): RedirectResponse {
        try {
            $entry = $entries->record($request->validated(), $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['operating_entry' => $exception->getMessage()]);
        }

        return redirect()
            ->route('cash.index')
            ->with('status', $entry->entry_type === 'expense'
                ? __('ui.expense_recorded')
                : __('ui.income_recorded'));
    }
}
