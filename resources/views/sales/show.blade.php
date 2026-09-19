@extends('layouts.app')

@section('title', $sale->number)
@section('page-title', $sale->number)

@section('content')
<div class="space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-2xl font-black">{{ $sale->number }}</h2>
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">{{ __('ui.status_completed') }}</span>
                <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">{{ __('ui.payment_'.$sale->payment_status->value) }}</span>
            </div>
            <div class="mt-2 text-sm text-slate-500">{{ $sale->sold_at->format('Y-m-d H:i') }} · {{ $sale->cashier->name }} · {{ $sale->customer_name_snapshot }}</div>
        </div>
        <a href="{{ route('pos.index') }}" class="btn-primary">{{ __('ui.back_to_pos') }}</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.subtotal') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($sale->subtotal) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.total_discount') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format(\App\Support\Decimal::add($sale->line_discount_total,$sale->sale_discount_amount,2)) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.net_total') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($sale->net_total) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.balance_due') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($sale->balance_due) }}</div></div>
    </div>

    @if(auth()->user()->hasPermission('reports.profit'))
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.cogs') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($sale->cogs_total) }}</div></div>
            <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.gross_profit') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($sale->gross_profit) }}</div></div>
        </div>
    @endif

    @if($sale->payments->isNotEmpty())
        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.payments') }}</h3></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($sale->payments as $payment)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 text-sm">
                        <div>
                            <div class="font-semibold">{{ $payment->paymentMethod->localizedName() }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $payment->paid_at->format('Y-m-d H:i') }} @if($payment->reference) · {{ $payment->reference }} @endif</div>
                        </div>
                        <div class="text-end">
                            <div class="font-black">{{ AppSupportMoney::format($payment->applied_amount) }}</div>
                            @if(AppSupportDecimal::isPositive($payment->change_amount))
                                <div class="mt-1 text-xs text-slate-500">{{ __('ui.change') }}: {{ AppSupportMoney::format($payment->change_amount) }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="panel overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.sale_items') }}</h3></div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40">
                    <tr>
                        <th class="px-5 py-3 text-start">{{ __('ui.product') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.unit') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.quantity') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.sale_price') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.discount') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.line_total') }}</th>
                        @if(auth()->user()->hasPermission('reports.profit'))<th class="px-5 py-3 text-end">{{ __('ui.cogs') }}</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($sale->items as $item)
                        <tr>
                            <td class="px-5 py-4"><div class="font-semibold">{{ $item->product_name_snapshot }}</div><div class="text-xs text-slate-500">{{ $item->sku_snapshot }}</div></td>
                            <td class="px-5 py-4">{{ $item->unit_name_snapshot }}</td>
                            <td class="px-5 py-4 text-end">{{ \App\Support\Decimal::display($item->quantity) }}</td>
                            <td class="px-5 py-4 text-end">{{ \App\Support\Money::format($item->unit_price) }}</td>
                            <td class="px-5 py-4 text-end">{{ \App\Support\Money::format(\App\Support\Decimal::add($item->line_discount_amount,$item->allocated_sale_discount,2)) }}</td>
                            <td class="px-5 py-4 text-end font-bold">{{ \App\Support\Money::format($item->line_net_total) }}</td>
                            @if(auth()->user()->hasPermission('reports.profit'))<td class="px-5 py-4 text-end">{{ \App\Support\Money::format($item->cogs_amount) }}</td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if(AppSupportDecimal::isPositive($sale->balance_due))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
            {{ __('ui.sale_balance_due_notice') }}
        </div>
    @endif
</div>
@endsection
