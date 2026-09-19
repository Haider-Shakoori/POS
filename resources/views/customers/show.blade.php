@extends('layouts.app')

@section('title', $customer->name)
@section('page-title', $customer->name)

@section('content')
<div class="space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <h2 class="text-2xl font-black">{{ $customer->name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $customer->phone ?: __('ui.no_phone') }}</p>
        </div>
        <a class="btn-secondary" href="{{ route('customers.index') }}">{{ __('ui.back_to_customers') }}</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.current_balance') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($customer->current_balance) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.credit_limit') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($customer->credit_limit) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.opening_balance') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($customer->opening_balance) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.credit_available') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format(max(0,(float)$customer->credit_limit-(float)$customer->current_balance)) }}</div></div>
    </div>

    @if(auth()->user()->hasPermission('customers.collect') && \App\Support\Decimal::isPositive($customer->current_balance))
        <section class="panel p-5">
            <h3 class="font-black">{{ __('ui.record_collection') }}</h3>
            <p class="mt-1 text-xs text-slate-500">{{ __('ui.collection_help') }}</p>

            <form method="POST" action="{{ route('customers.collections.store',$customer) }}" class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.amount_afn') }}</label>
                    <input class="field" name="amount" value="{{ old('amount') }}" inputmode="decimal" required>
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
                    <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.cash_tendered_optional') }}</label>
                    <input class="field" name="tendered_amount" value="{{ old('tendered_amount') }}" inputmode="decimal">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.payment_reference') }}</label>
                    <input class="field" name="reference" value="{{ old('reference') }}">
                </div>
                <div class="flex items-end">
                    <button class="btn-primary w-full" type="submit">{{ __('ui.record_collection') }}</button>
                </div>
            </form>

            @error('collection')
                <p class="mt-3 text-sm font-semibold text-red-600">{{ $message }}</p>
            @enderror
        </section>
    @endif

    <div class="grid gap-5 xl:grid-cols-[1.3fr_1fr]">
        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.customer_ledger') }}</h3></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('ui.date') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('ui.type') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('ui.debit') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('ui.credit') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('ui.balance') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($customer->ledgerEntries as $entry)
                            <tr>
                                <td class="px-4 py-3">{{ $entry->occurred_at->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3"><div class="font-semibold">{{ __('ui.ledger_'.$entry->entry_type) }}</div><div class="text-xs text-slate-500">{{ $entry->reference_number }}</div></td>
                                <td class="px-4 py-3 text-end">{{ \App\Support\Money::format($entry->debit) }}</td>
                                <td class="px-4 py-3 text-end">{{ \App\Support\Money::format($entry->credit) }}</td>
                                <td class="px-4 py-3 text-end font-bold">{{ \App\Support\Money::format($entry->balance_after) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">{{ __('ui.no_ledger_entries') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.outstanding_sales') }}</h3></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($customer->sales->where('balance_due','>',0) as $sale)
                    <a class="block px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/40" href="{{ route('sales.show',$sale) }}">
                        <div class="flex items-center justify-between gap-3"><strong>{{ $sale->number }}</strong><strong>{{ \App\Support\Money::format($sale->balance_due) }}</strong></div>
                        <div class="mt-1 text-xs text-slate-500">{{ $sale->sold_at->format('Y-m-d H:i') }} · {{ __('ui.payment_'.$sale->payment_status->value) }}</div>
                    </a>
                @empty
                    <div class="p-8 text-center text-sm text-slate-400">{{ __('ui.no_outstanding_sales') }}</div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
