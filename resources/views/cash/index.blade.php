@extends('layouts.app')

@section('title', __('ui.cash_drawer'))
@section('page-title', __('ui.cash_drawer'))

@section('content')
<div class="space-y-5">
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
        <div>
            <h2 class="text-2xl font-black">{{ __('ui.cash_drawer') }}</h2>
            <p class="mt-1 max-w-3xl text-sm text-slate-500">{{ __('ui.cash_drawer_help') }}</p>
        </div>
        @if($shift)
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                {{ __('ui.shift_open') }} · {{ $shift->terminal->name }}
            </div>
        @endif
    </div>

    @error('shift')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</div>
    @enderror
    @error('operating_entry')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</div>
    @enderror
    @error('cash_movement')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</div>
    @enderror

    @if(!$shift)
        <section class="panel p-5">
            <div class="max-w-2xl">
                <h3 class="text-lg font-black">{{ __('ui.open_cashier_shift') }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ __('ui.open_shift_help') }}</p>

                @if(auth()->user()->hasPermission('shifts.open'))
                    <form method="POST" action="{{ route('cash.shifts.store') }}" class="mt-5 grid gap-4 sm:grid-cols-3">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.terminal') }}</label>
                            <select class="field" name="terminal_id" required>
                                @foreach($terminals as $terminal)
                                    <option value="{{ $terminal->id }}">{{ $terminal->name }} · {{ $terminal->code }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.opening_cash') }}</label>
                            <input class="field" name="opening_cash" value="{{ old('opening_cash', '0.00') }}" inputmode="decimal" required>
                        </div>
                        <div class="flex items-end">
                            <button class="btn-primary w-full" type="submit">{{ __('ui.open_shift') }}</button>
                        </div>
                    </form>
                @else
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
                        {{ __('ui.shift_open_permission_required') }}
                    </div>
                @endif
            </div>
        </section>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="stat-card">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.opening_cash') }}</div>
                <div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($shift->opening_cash) }}</div>
            </div>
            <div class="stat-card">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.expected_cash') }}</div>
                <div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($shift->expected_cash ?? '0.00') }}</div>
            </div>
            <div class="stat-card">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.terminal') }}</div>
                <div class="mt-2 text-xl font-black">{{ $shift->terminal->name }}</div>
            </div>
            <div class="stat-card">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.opened_at') }}</div>
                <div class="mt-2 text-lg font-black">{{ $shift->opened_at->format('Y-m-d H:i') }}</div>
            </div>
        </div>

        @if(auth()->user()->hasPermission('cash.manage'))
            <section class="panel p-5">
                <h3 class="font-black">{{ __('ui.manual_cash_movement') }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ __('ui.manual_cash_help') }}</p>

                <form method="POST" action="{{ route('cash.movements.store', $shift) }}" class="mt-4 grid gap-4 md:grid-cols-4">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.movement_type') }}</label>
                        <select class="field" name="movement_type" required>
                            <option value="cash_deposit">{{ __('ui.cash_deposit') }}</option>
                            <option value="cash_withdrawal">{{ __('ui.cash_withdrawal') }}</option>
                            <option value="drawer_to_safe">{{ __('ui.drawer_to_safe') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.amount_afn') }}</label>
                        <input class="field" name="amount" inputmode="decimal" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.reason') }}</label>
                        <input class="field" name="reason" required maxlength="1000">
                    </div>
                    <div class="flex items-end">
                        <button class="btn-secondary w-full" type="submit">{{ __('ui.record_movement') }}</button>
                    </div>
                </form>
            </section>
        @endif

        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="font-black">{{ __('ui.cash_movements') }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ __('ui.cash_movements_help') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40">
                        <tr>
                            <th class="px-5 py-3 text-start">{{ __('ui.date') }}</th>
                            <th class="px-5 py-3 text-start">{{ __('ui.type') }}</th>
                            <th class="px-5 py-3 text-start">{{ __('ui.reference') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('ui.inflow') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('ui.outflow') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('ui.expected_cash') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($shift->cashMovements as $movement)
                            <tr>
                                <td class="px-5 py-4">{{ $movement->occurred_at->format('Y-m-d H:i') }}</td>
                                <td class="px-5 py-4">
                                    <div class="font-semibold">{{ __('ui.cash_type_'.$movement->movement_type) }}</div>
                                    @if($movement->reason)<div class="mt-1 text-xs text-slate-500">{{ $movement->reason }}</div>@endif
                                </td>
                                <td class="px-5 py-4">{{ $movement->reference_number ?: '—' }}</td>
                                <td class="px-5 py-4 text-end font-bold text-emerald-700 dark:text-emerald-300">
                                    {{ $movement->direction === 'inflow' ? \App\Support\Money::format($movement->amount) : '—' }}
                                </td>
                                <td class="px-5 py-4 text-end font-bold text-red-600 dark:text-red-300">
                                    {{ $movement->direction === 'outflow' ? \App\Support\Money::format($movement->amount) : '—' }}
                                </td>
                                <td class="px-5 py-4 text-end font-black">{{ \App\Support\Money::format($movement->expected_cash_after) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">{{ __('ui.no_cash_movements') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if(auth()->user()->hasPermission('expenses.create'))
        <div class="grid gap-5 xl:grid-cols-2">
            <section class="panel p-5">
                <h3 class="font-black">{{ __('ui.record_expense') }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ __('ui.expense_help') }}</p>

                <form method="POST" action="{{ route('cash.operating-entries.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <input type="hidden" name="entry_type" value="expense">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.category') }}</label>
                        <select class="field" name="expense_category_id" required>
                            @foreach($expenseCategories as $category)
                                <option value="{{ $category->id }}">{{ $category->localizedName() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.payment_method') }}</label>
                        <select class="field" name="payment_method_id" required>
                            @foreach($paymentMethods as $method)
                                <option value="{{ $method->id }}">{{ $method->localizedName() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.amount_afn') }}</label>
                        <input class="field" name="amount" inputmode="decimal" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.reference') }}</label>
                        <input class="field" name="reference">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.description') }}</label>
                        <input class="field" name="description">
                    </div>
                    <button class="btn-primary sm:col-span-2" type="submit">{{ __('ui.record_expense') }}</button>
                </form>
            </section>

            <section class="panel p-5">
                <h3 class="font-black">{{ __('ui.record_other_income') }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ __('ui.other_income_help') }}</p>

                <form method="POST" action="{{ route('cash.operating-entries.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <input type="hidden" name="entry_type" value="income">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.category') }}</label>
                        <select class="field" name="expense_category_id" required>
                            @foreach($incomeCategories as $category)
                                <option value="{{ $category->id }}">{{ $category->localizedName() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.payment_method') }}</label>
                        <select class="field" name="payment_method_id" required>
                            @foreach($paymentMethods as $method)
                                <option value="{{ $method->id }}">{{ $method->localizedName() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.amount_afn') }}</label>
                        <input class="field" name="amount" inputmode="decimal" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.reference') }}</label>
                        <input class="field" name="reference">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.description') }}</label>
                        <input class="field" name="description">
                    </div>
                    <button class="btn-secondary sm:col-span-2" type="submit">{{ __('ui.record_other_income') }}</button>
                </form>
            </section>
        </div>
    @endif

    @if(auth()->user()->hasPermission('expenses.view'))
        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.recent_operating_entries') }}</h3></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40">
                        <tr>
                            <th class="px-5 py-3 text-start">{{ __('ui.date') }}</th>
                            <th class="px-5 py-3 text-start">{{ __('ui.type') }}</th>
                            <th class="px-5 py-3 text-start">{{ __('ui.category') }}</th>
                            <th class="px-5 py-3 text-start">{{ __('ui.payment_method') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('ui.amount_afn') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($recentEntries as $entry)
                            <tr>
                                <td class="px-5 py-4">{{ $entry->occurred_at->format('Y-m-d H:i') }}</td>
                                <td class="px-5 py-4">{{ __('ui.operating_'.$entry->entry_type) }}</td>
                                <td class="px-5 py-4">{{ $entry->category->localizedName() }}</td>
                                <td class="px-5 py-4">{{ $entry->paymentMethod->localizedName() }}</td>
                                <td class="px-5 py-4 text-end font-bold">{{ \App\Support\Money::format($entry->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">{{ __('ui.no_operating_entries') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white p-4 text-xs leading-5 text-slate-500 dark:border-slate-800 dark:bg-slate-900">
        {{ __('ui.batch9_closing_notice') }}
    </div>
</div>
@endsection
