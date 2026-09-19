@extends('layouts.app')

@section('title', $supplier->name)
@section('page-title', $supplier->name)

@section('content')
<div class="space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <h2 class="text-2xl font-black">{{ $supplier->name }}</h2>
            <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-slate-500">
                <span>{{ __('ui.contact_person') }}: {{ $supplier->contact_person ?: '—' }}</span>
                <span>{{ __('ui.phone') }}: {{ $supplier->phone ?: '—' }}</span>
            </div>
        </div>
        <a href="{{ route('purchasing.suppliers.index') }}" class="btn-secondary">{{ __('ui.back_to_suppliers') }}</a>
    </div>

    @error('payment')
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</div>
    @enderror

    @php
        $supplierCredit = \App\Support\Decimal::isNegative($supplier->current_balance);
        $displayBalance = $supplierCredit
            ? ltrim((string) $supplier->current_balance, '-')
            : (string) $supplier->current_balance;
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">
                {{ $supplierCredit ? __('ui.supplier_credit') : __('ui.supplier_payable') }}
            </div>
            <div class="mt-2 text-2xl font-black {{ $supplierCredit ? 'text-emerald-600 dark:text-emerald-300' : '' }}">
                {{ \App\Support\Money::format($displayBalance) }}
            </div>
        </div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.opening_balance') }}</div><div class="mt-2 text-2xl font-black">{{ \App\Support\Money::format($supplier->opening_balance) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.purchase_orders') }}</div><div class="mt-2 text-2xl font-black">{{ $supplier->purchase_orders_count }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.goods_receipts') }}</div><div class="mt-2 text-2xl font-black">{{ $supplier->goods_receipts_count }}</div></div>
    </div>

    @if(auth()->user()->hasPermission('suppliers.pay') && \App\Support\Decimal::isPositive($supplier->current_balance))
        <section class="panel p-5">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                <div>
                    <h3 class="text-lg font-black">{{ __('ui.record_supplier_payment') }}</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ __('ui.supplier_payment_help') }}</p>
                </div>
                <div class="text-sm text-slate-500">{{ __('ui.maximum') }}: <strong>{{ \App\Support\Money::format($supplier->current_balance) }}</strong></div>
            </div>

            <form method="POST" action="{{ route('purchasing.suppliers.payments.store', $supplier) }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.amount') }}</label>
                    <input class="field" name="amount" value="{{ old('amount') }}" inputmode="decimal" required>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.payment_method') }}</label>
                    <select class="field" name="method" required>
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method->value }}" @selected(old('method') === $method->value)>{{ __('ui.payment_'.$method->value) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.payment_reference') }}</label>
                    <input class="field" name="reference" value="{{ old('reference') }}">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.payment_date') }}</label>
                    <input class="field" type="datetime-local" name="paid_at" value="{{ old('paid_at') }}">
                </div>

                <div class="flex items-end">
                    <button class="btn-primary w-full" type="submit">{{ __('ui.record_payment') }}</button>
                </div>

                <div class="md:col-span-2 xl:col-span-5">
                    <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.notes') }}</label>
                    <input class="field" name="notes" value="{{ old('notes') }}">
                </div>
            </form>
        </section>
    @endif

    <div class="grid gap-5 xl:grid-cols-2">
        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.recent_goods_receipts') }}</h3></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($supplier->goodsReceipts as $receipt)
                    <a href="{{ route('purchasing.receipts.show', $receipt) }}" class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <div><div class="font-semibold">{{ $receipt->number }}</div><div class="text-xs text-slate-500">{{ $receipt->received_at->format('Y-m-d H:i') }}</div></div>
                        <div class="text-end"><div class="font-bold">{{ \App\Support\Money::format($receipt->net_total) }}</div><div class="text-xs text-slate-500">{{ __('ui.balance_due') }} {{ \App\Support\Money::format($receipt->balance_due) }}</div></div>
                    </a>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-400">{{ __('ui.no_goods_receipts') }}</div>
                @endforelse
            </div>
        </section>

        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.supplier_payments') }}</h3></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($supplier->supplierPayments as $payment)
                    <div class="px-5 py-4 text-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-bold">{{ $payment->number }} · {{ __('ui.payment_'.$payment->method->value) }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $payment->paid_at->format('Y-m-d H:i') }} @if($payment->reference) · {{ $payment->reference }} @endif</div>
                            </div>
                            <div class="font-black">{{ \App\Support\Money::format($payment->amount) }}</div>
                        </div>
                        @if($payment->allocations->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-500">
                                @foreach($payment->allocations as $allocation)
                                    <span class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800">{{ $allocation->goodsReceipt->number }} · {{ \App\Support\Money::format($allocation->amount) }}</span>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-2 text-xs text-slate-500">{{ __('ui.payment_applied_to_opening_balance') }}</div>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-400">{{ __('ui.no_supplier_payments') }}</div>
                @endforelse
            </div>
        </section>
    </div>

    @if($supplier->purchaseReturns->isNotEmpty())
        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.purchase_returns') }}</h3></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($supplier->purchaseReturns as $return)
                    <a href="{{ route('purchasing.receipts.show', $return->goodsReceipt) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 text-sm hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <div><div class="font-bold">{{ $return->number }}</div><div class="mt-1 text-xs text-slate-500">{{ $return->posted_at->format('Y-m-d H:i') }} · {{ $return->goodsReceipt->number }} · {{ $return->reason }}</div></div>
                        <div class="font-black">{{ \App\Support\Money::format($return->return_total) }}</div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="panel overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <h3 class="font-black">{{ __('ui.supplier_ledger') }}</h3>
            <p class="mt-1 text-xs text-slate-500">{{ __('ui.supplier_ledger_help') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40">
                    <tr>
                        <th class="px-5 py-3 text-start">{{ __('ui.date') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.transaction') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.reference') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.debit') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.credit') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($supplier->ledgerEntries as $entry)
                        <tr>
                            <td class="px-5 py-4 whitespace-nowrap">{{ $entry->occurred_at->format('Y-m-d H:i') }}</td>
                            <td class="px-5 py-4">{{ __('ui.supplier_ledger_'.$entry->entry_type) }}</td>
                            <td class="px-5 py-4">{{ $entry->reference_number ?: '—' }}</td>
                            <td class="px-5 py-4 text-end">{{ \App\Support\Decimal::isPositive($entry->debit) ? \App\Support\Money::format($entry->debit) : '—' }}</td>
                            <td class="px-5 py-4 text-end">{{ \App\Support\Decimal::isPositive($entry->credit) ? \App\Support\Money::format($entry->credit) : '—' }}</td>
                            <td class="px-5 py-4 text-end font-bold">{{ \App\Support\Money::format($entry->balance_after) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">{{ __('ui.no_supplier_ledger_entries') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
