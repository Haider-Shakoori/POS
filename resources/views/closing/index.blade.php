@extends('layouts.app')

@section('title', __('ui.daily_closing'))
@section('page-title', __('ui.daily_closing'))

@section('content')
@section('page-errors', '1')
<div class="space-y-5">
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
        <div>
            <h2 class="text-2xl font-black">{{ __('ui.daily_closing') }}</h2>
            <p class="mt-1 max-w-3xl text-sm text-slate-500">{{ __('ui.daily_closing_help') }}</p>
        </div>

        <form method="GET" action="{{ route('closing.index') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.business_date') }}</label>
                <input class="field" type="date" name="date" value="{{ $date }}" required>
            </div>
            <button class="btn-secondary" type="submit">{{ __('ui.load_date') }}</button>
        </form>
    </div>

    @error('business_day_close')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</div>
    @enderror
    @error('business_day_reopen')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</div>
    @enderror
    @error('shift_reopen')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</div>
    @enderror

    @php
        $isClosed = $day?->status?->value === 'closed';
        $money = fn ($value) => \App\Support\Money::format($value ?? '0.00');
    @endphp

    <section class="panel p-5">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
            <div>
                <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.business_date') }}</div>
                <div class="mt-1 text-xl font-black">{{ $date }}</div>
                <div class="mt-2">
                    @if($isClosed)
                        <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ __('ui.day_closed') }}</span>
                    @else
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{{ __('ui.day_open') }}</span>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @if(!$isClosed && auth()->user()->hasPermission('business_days.close'))
                    <form method="POST" action="{{ route('closing.close', ['date' => $date]) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.closing_notes') }}</label>
                            <input class="field" name="notes" maxlength="2000" placeholder="{{ __('ui.optional') }}">
                        </div>
                        <button class="btn-primary" type="submit" @disabled($summary['open_shift_count'] > 0)>
                            {{ __('ui.close_business_day') }}
                        </button>
                    </form>
                @endif

                @if($isClosed && auth()->user()->hasPermission('business_days.reopen'))
                    <form method="POST" action="{{ route('closing.reopen', ['date' => $date]) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.reopen_reason') }}</label>
                            <input class="field" name="reason" maxlength="1000" required>
                        </div>
                        <button class="btn-secondary" type="submit">{{ __('ui.reopen_business_day') }}</button>
                    </form>
                @endif
            </div>
        </div>

        @if(!$isClosed && $summary['open_shift_count'] > 0)
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
                {{ __('ui.close_all_shifts_first', ['count' => $summary['open_shift_count']]) }}
            </div>
        @endif

        @if($latestClosure)
            <div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm dark:bg-slate-950/40">
                <span class="font-bold">{{ __('ui.latest_close') }}:</span>
                {{ $latestClosure->number }} · {{ __('ui.revision') }} {{ $latestClosure->version }} ·
                {{ $latestClosure->closed_at->format('Y-m-d H:i') }}
            </div>
        @endif
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <div class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.net_sales') }}</div>
            <div class="mt-2 text-xl font-black">{{ $money($summary['net_sales_total']) }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.gross_profit') }}</div>
            <div class="mt-2 text-xl font-black">{{ $money($summary['gross_profit_total']) }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.net_profit') }}</div>
            <div class="mt-2 text-xl font-black">{{ $money($summary['net_profit_total']) }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.expected_cash') }}</div>
            <div class="mt-2 text-xl font-black">{{ $money($summary['expected_cash_total']) }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.actual_cash') }}</div>
            <div class="mt-2 text-xl font-black">{{ $money($summary['actual_cash_total']) }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.variance') }}</div>
            <div class="mt-2 text-xl font-black {{ \App\Support\Decimal::compare($summary['variance_total'], '0') === 0 ? '' : 'text-amber-600' }}">
                {{ $money($summary['variance_total']) }}
            </div>
        </div>
    </div>

    <section class="panel overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <h3 class="font-black">{{ __('ui.financial_summary') }}</h3>
            <p class="mt-1 text-xs text-slate-500">{{ __('ui.financial_summary_help') }}</p>
        </div>
        <div class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4 dark:bg-slate-800">
            @foreach([
                __('ui.sales_count') => $summary['sales_count'],
                __('ui.sales_subtotal') => $money($summary['sales_subtotal']),
                __('ui.line_discounts') => $money($summary['sales_line_discount_total']),
                __('ui.sale_discounts') => $money($summary['sales_discount_total']),
                __('ui.sales_net_total') => $money($summary['sales_net_total']),
                __('ui.sales_returns') => $money($summary['sales_return_total']),
                __('ui.customer_collections') => $money($summary['customer_collections_total']),
                __('ui.purchases') => $money($summary['purchases_total']),
                __('ui.purchase_returns') => $money($summary['purchase_returns_total']),
                __('ui.supplier_payments') => $money($summary['supplier_payments_total']),
                __('ui.operating_expenses') => $money($summary['operating_expenses_total']),
                __('ui.other_income') => $money($summary['other_income_total']),
                __('ui.sales_cogs') => $money($summary['sales_cogs_total']),
                __('ui.cogs_reversed') => $money($summary['cogs_reversed_total']),
                __('ui.net_cogs') => $money($summary['net_cogs_total']),
                __('ui.gross_profit') => $money($summary['gross_profit_total']),
            ] as $label => $value)
                <div class="bg-white p-4 dark:bg-slate-900">
                    <div class="text-xs font-semibold text-slate-500">{{ $label }}</div>
                    <div class="mt-1 font-black">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="panel overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <h3 class="font-black">{{ __('ui.cash_reconciliation') }}</h3>
            <p class="mt-1 text-xs text-slate-500">{{ __('ui.cash_reconciliation_help') }}</p>
        </div>
        <div class="grid gap-px bg-slate-200 sm:grid-cols-2 lg:grid-cols-4 dark:bg-slate-800">
            @foreach([
                __('ui.opening_cash') => $money($summary['opening_cash_total']),
                __('ui.cash_inflows_ex_opening') => $money($summary['cash_inflow_total']),
                __('ui.cash_outflows') => $money($summary['cash_outflow_total']),
                __('ui.ledger_expected_cash') => $money($summary['ledger_expected_cash_total']),
            ] as $label => $value)
                <div class="bg-white p-4 dark:bg-slate-900">
                    <div class="text-xs font-semibold text-slate-500">{{ $label }}</div>
                    <div class="mt-1 font-black">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        @if(\App\Support\Decimal::compare($summary['expected_cash_total'], $summary['ledger_expected_cash_total']) !== 0)
            <div class="m-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                {{ __('ui.cash_reconciliation_mismatch') }}
            </div>
        @endif
    </section>

    <section class="panel overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <h3 class="font-black">{{ __('ui.cashier_shifts') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40">
                    <tr>
                        <th class="px-5 py-3 text-start">{{ __('ui.cashier') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.terminal') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.status') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.expected_cash') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.actual_cash') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.variance') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.closed_at') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($shifts as $shift)
                        <tr>
                            <td class="px-5 py-4 font-semibold">{{ $shift->user->name }}</td>
                            <td class="px-5 py-4">{{ $shift->terminal->name }}</td>
                            <td class="px-5 py-4">
                                @if($shift->status->value === 'open')
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{{ __('ui.shift_open') }}</span>
                                @else
                                    <span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ __('ui.shift_closed_status') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-end font-bold">{{ $money($shift->expected_cash) }}</td>
                            <td class="px-5 py-4 text-end font-bold">{{ $shift->actual_cash !== null ? $money($shift->actual_cash) : '—' }}</td>
                            <td class="px-5 py-4 text-end font-bold">
                                {{ $shift->variance !== null ? $money($shift->variance) : '—' }}
                                @if($shift->variance_within_tolerance === false)
                                    <div class="mt-1 text-xs text-amber-600">{{ __('ui.outside_tolerance') }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4">{{ $shift->closed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-5 py-4 text-end">
                                @if(!$isClosed && $shift->status->value === 'closed' && auth()->user()->hasPermission('shifts.reopen'))
                                    <form method="POST" action="{{ route('cash.shifts.reopen', $shift) }}" class="inline-flex items-center gap-2">
                                        @csrf
                                        <input class="field w-44" name="reason" placeholder="{{ __('ui.reopen_reason') }}" required maxlength="1000">
                                        <button class="btn-secondary" type="submit">{{ __('ui.reopen') }}</button>
                                    </form>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                        @if($shift->variance_reason)
                            <tr class="bg-amber-50/60 dark:bg-amber-950/20">
                                <td colspan="8" class="px-5 py-3 text-xs text-amber-800 dark:text-amber-300">
                                    <span class="font-bold">{{ __('ui.variance_reason') }}:</span> {{ $shift->variance_reason }}
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="8" class="px-5 py-10 text-center text-slate-400">{{ __('ui.no_shifts_for_date') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <h3 class="font-black">{{ __('ui.closure_history') }}</h3>
            <p class="mt-1 text-xs text-slate-500">{{ __('ui.closure_history_help') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40">
                    <tr>
                        <th class="px-5 py-3 text-start">{{ __('ui.document_number') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.revision') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.closed_by') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.closed_at') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.net_sales') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.net_profit') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.expected_cash') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.actual_cash') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.variance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($closures as $closure)
                        <tr>
                            <td class="px-5 py-4 font-bold">{{ $closure->number }}</td>
                            <td class="px-5 py-4">{{ $closure->version }}</td>
                            <td class="px-5 py-4">{{ $closure->closedBy->name }}</td>
                            <td class="px-5 py-4">{{ $closure->closed_at->format('Y-m-d H:i') }}</td>
                            <td class="px-5 py-4 text-end">{{ $money($closure->net_sales_total) }}</td>
                            <td class="px-5 py-4 text-end font-bold">{{ $money($closure->net_profit_total) }}</td>
                            <td class="px-5 py-4 text-end">{{ $money($closure->expected_cash_total) }}</td>
                            <td class="px-5 py-4 text-end">{{ $money($closure->actual_cash_total) }}</td>
                            <td class="px-5 py-4 text-end">{{ $money($closure->variance_total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-10 text-center text-slate-400">{{ __('ui.no_closure_history') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
