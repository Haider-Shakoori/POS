@extends('layouts.app')

@section('title', $receipt->number)
@section('page-title', $receipt->number)

@section('content')
<div class="space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <div class="flex items-center gap-3"><h2 class="text-2xl font-black">{{ $receipt->number }}</h2><span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">{{ __('ui.posted') }}</span></div>
            <div class="mt-2 text-sm text-slate-500">{{ $receipt->supplier->name }} · {{ $receipt->received_at->format('Y-m-d H:i') }} · {{ $receipt->purchaseOrder?->number ?? __('ui.direct_receipt') }}</div>
        </div>
        <a href="{{ route('purchasing.receipts.index') }}" class="btn-secondary">{{ __('ui.back') }}</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.subtotal') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($receipt->subtotal) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.total_discount') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format(\App\Support\Decimal::add($receipt->line_discount_total,$receipt->receipt_discount_amount,2)) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.purchase_expenses') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($receipt->expense_total) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.paid_amount') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($receipt->paid_amount) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.balance_due') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($receipt->balance_due) }}</div></div>
    </div>

    <section class="panel overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="font-black">{{ __('ui.received_items') }}</h3>
                <div class="text-lg font-black">{{ __('ui.net_total') }}: {{ \App\Support\Money::format($receipt->net_total) }}</div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40">
                    <tr>
                        <th class="px-5 py-3 text-start">{{ __('ui.product') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.unit') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.quantity') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.invoice_unit_cost') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.allocated_expense') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.base_landed_cost') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.batch') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($receipt->items as $item)
                        <tr>
                            <td class="px-5 py-4 font-semibold">{{ $item->product->localizedName() }}</td>
                            <td class="px-5 py-4">{{ $item->productUnit->unit->localizedName() }}</td>
                            <td class="px-5 py-4 text-end">{{ \App\Support\Decimal::display($item->quantity) }}</td>
                            <td class="px-5 py-4 text-end">{{ \App\Support\Money::format(\App\Support\Decimal::round($item->source_unit_cost,2)) }}</td>
                            <td class="px-5 py-4 text-end">{{ \App\Support\Money::format($item->allocated_expense) }}</td>
                            <td class="px-5 py-4 text-end font-bold">{{ \App\Support\Money::format(\App\Support\Decimal::round($item->base_unit_landed_cost,2)) }}</td>
                            <td class="px-5 py-4">@if($item->batch_number)<div class="font-mono">{{ $item->batch_number }}</div><div class="text-xs text-slate-500">{{ $item->expires_at?->format('Y-m-d') }}</div>@else — @endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-2">
        <section class="panel p-5">
            <h3 class="font-black">{{ __('ui.purchase_expenses') }}</h3>
            <div class="mt-4 space-y-3">
                @forelse($receipt->expenses as $expense)
                    <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60"><span>{{ __('ui.expense_'.$expense->type->value) }}{{ $expense->description ? ' · '.$expense->description : '' }}</span><strong>{{ \App\Support\Money::format($expense->amount) }}</strong></div>
                @empty
                    <div class="text-sm text-slate-400">{{ __('ui.no_purchase_expenses') }}</div>
                @endforelse
            </div>
        </section>

        <section class="panel p-5">
            <h3 class="font-black">{{ __('ui.payment') }}</h3>
            <div class="mt-4 space-y-3">
                @forelse($receipt->payments as $payment)
                    <div class="rounded-xl bg-slate-50 px-3 py-3 text-sm dark:bg-slate-800/60">
                        <div class="flex justify-between"><span>{{ __('ui.payment_'.$payment->method->value) }}</span><strong>{{ \App\Support\Money::format($payment->amount) }}</strong></div>
                        @if($payment->reference)<div class="mt-1 text-xs text-slate-500">{{ $payment->reference }}</div>@endif
                    </div>
                @empty
                    <div class="text-sm text-slate-400">{{ __('ui.no_initial_payment') }}</div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
        {{ __('ui.receipt_stock_posted_notice') }}
    </div>
</div>
@endsection
