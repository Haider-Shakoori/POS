<?php

namespace App\Http\Controllers\Closing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Closing\CloseBusinessDayRequest;
use App\Http\Requests\Closing\ReopenBusinessDayRequest;
use App\Models\BusinessDay;
use App\Models\BusinessDayClosure;
use App\Models\CashierShift;
use App\Services\Closing\BusinessDayClosingService;
use App\Services\Closing\BusinessDayService;
use App\Services\Closing\BusinessDaySummaryService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessDayClosingController extends Controller
{
    public function index(
        Request $request,
        BusinessDaySummaryService $summaries,
    ): View {
        $date = $request->filled('date')
            ? CarbonImmutable::parse((string) $request->input('date'))->format('Y-m-d')
            : now()->format('Y-m-d');

        $day = BusinessDay::query()
            ->whereDate('business_date', $date)
            ->first();

        $shifts = CashierShift::query()
            ->with(['user', 'terminal', 'closures' => fn ($query) => $query->latest('version')])
            ->whereDate('business_date', $date)
            ->orderBy('opened_at')
            ->get();

        $closures = BusinessDayClosure::query()
            ->with('closedBy')
            ->whereHas('businessDay', fn ($query) => $query->whereDate('business_date', $date))
            ->latest('version')
            ->get();

        return view('closing.index', [
            'date' => $date,
            'day' => $day,
            'summary' => $summaries->summarize($date),
            'shifts' => $shifts,
            'closures' => $closures,
            'latestClosure' => $closures->first(),
        ]);
    }

    public function close(
        CloseBusinessDayRequest $request,
        string $date,
        BusinessDayClosingService $closing,
    ): RedirectResponse {
        try {
            $closing->close($date, $request->validated(), $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['business_day_close' => $exception->getMessage()]);
        }

        return redirect()
            ->route('closing.index', ['date' => $date])
            ->with('status', __('ui.business_day_closed'));
    }

    public function reopen(
        ReopenBusinessDayRequest $request,
        string $date,
        BusinessDayService $days,
    ): RedirectResponse {
        try {
            $days->reopen(
                $date,
                $request->string('reason')->toString(),
                $request->user(),
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['business_day_reopen' => $exception->getMessage()]);
        }

        return redirect()
            ->route('closing.index', ['date' => $date])
            ->with('status', __('ui.business_day_reopened'));
    }
}
