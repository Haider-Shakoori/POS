@extends('layouts.app')

@section('title', $sale->number)
@section('page-title', $sale->number)

@section('content')
@section('page-errors', '1')
<div class="space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-2xl font-black">{{ $sale->number }}</h2>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                    {{ __('ui.sale_status_'.$sale->status->value) }}
                </span>
                <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">
                    {{ __('ui.payment_'.$sale->payment_status->value) }}
                </span>
            </div>
            <div class="mt-2 text-sm text-slate-500">
                {{ $sale->sold_at->format('Y-m-d H:i') }} · {{ $sale->cashier->name }} · {{ $sale->customer_name_snapshot }}
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('sales.receipt', $sale) }}" target="_blank" class="btn-secondary">{{ __('ui.print_receipt') }}</a>
            <a href="{{ route('sales.index') }}" class="btn-secondary">{{ __('ui.sales_history') }}</a>
            <a href="{{ route('pos.index') }}" class="btn-primary">{{ __('ui.back_to_pos') }}</a>
        </div>
    </div>

    @error('return')
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</div>
    @enderror
    @error('void')
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</div>
    @enderror

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.net_total') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($sale->net_total) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.returned_total') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($sale->returned_total) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.balance_due') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($sale->balance_due) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.refunded_total') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($sale->refunded_total) }}</div></div>
    </div>

    @if(auth()->user()->hasPermission('reports.profit'))
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.cogs') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($sale->cogs_total) }}</div></div>
            <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.gross_profit') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($sale->gross_profit) }}</div></div>
            <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.cogs_reversed') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($cogsReversedTotal) }}</div></div>
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
                            <div class="font-black">{{ \App\Support\Money::format($payment->applied_amount) }}</div>
                            @if(\App\Support\Decimal::isPositive($payment->change_amount))
                                <div class="mt-1 text-xs text-slate-500">{{ __('ui.change') }}: {{ \App\Support\Money::format($payment->change_amount) }}</div>
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
                        <th class="px-5 py-3 text-end">{{ __('ui.returned_quantity') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.returnable_quantity') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.line_total') }}</th>
                        @if(auth()->user()->hasPermission('reports.profit'))<th class="px-5 py-3 text-end">{{ __('ui.cogs') }}</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($sale->items as $item)
                        @php
                            $returnedQty = \App\Support\Decimal::normalize((string) $item->returnItems->sum('quantity'));
                            $returnableQty = \App\Support\Decimal::subtract($item->quantity, $returnedQty);
                        @endphp
                        <tr>
                            <td class="px-5 py-4"><div class="font-semibold">{{ $item->product_name_snapshot }}</div><div class="text-xs text-slate-500">{{ $item->sku_snapshot }}</div></td>
                            <td class="px-5 py-4">{{ $item->unit_name_snapshot }}</td>
                            <td class="px-5 py-4 text-end">{{ \App\Support\Decimal::display($item->quantity) }}</td>
                            <td class="px-5 py-4 text-end">{{ \App\Support\Decimal::display($returnedQty) }}</td>
                            <td class="px-5 py-4 text-end font-bold">{{ \App\Support\Decimal::display($returnableQty) }}</td>
                            <td class="px-5 py-4 text-end font-bold">{{ \App\Support\Money::format($item->line_net_total) }}</td>
                            @if(auth()->user()->hasPermission('reports.profit'))<td class="px-5 py-4 text-end">{{ \App\Support\Money::format($item->cogs_amount) }}</td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if($sale->returns->isNotEmpty())
        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.reversal_history') }}</h3></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($sale->returns->sortByDesc('posted_at') as $return)
                    <div class="grid gap-3 px-5 py-4 text-sm md:grid-cols-[1fr_auto_auto_auto] md:items-center">
                        <div>
                            <div class="font-bold">{{ $return->number }} · {{ __('ui.reversal_type_'.$return->type) }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $return->posted_at->format('Y-m-d H:i') }} · {{ $return->reason }}</div>
                        </div>
                        <div class="md:text-end"><span class="text-xs text-slate-500">{{ __('ui.return_total') }}</span><div class="font-bold">{{ \App\Support\Money::format($return->return_total) }}</div></div>
                        <div class="md:text-end"><span class="text-xs text-slate-500">{{ __('ui.receivable_reversed') }}</span><div class="font-bold">{{ \App\Support\Money::format($return->receivable_reversed) }}</div></div>
                        <div class="md:text-end"><span class="text-xs text-slate-500">{{ __('ui.refund_total') }}</span><div class="font-bold">{{ \App\Support\Money::format($return->refund_total) }}</div></div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @php
        $hasReturnable = $sale->items->contains(function ($item) {
            $returned = \App\Support\Decimal::normalize((string) $item->returnItems->sum('quantity'));
            return \App\Support\Decimal::compare($item->quantity, $returned) > 0;
        });
    @endphp

    @if($hasReturnable && $sale->status !== \App\Enums\SaleStatus::Voided)
        <div class="grid gap-5 xl:grid-cols-2">
            @if(auth()->user()->hasPermission('sales.return'))
                <section class="panel p-5" x-data="{ selected: {} }">
                    <h3 class="text-lg font-black">{{ __('ui.return_items') }}</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ __('ui.return_items_help') }}</p>

                    <form method="POST" action="{{ route('sales.returns.store',$sale) }}" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                        <div class="space-y-2">
                            @foreach($sale->items as $item)
                                @php
                                    $returnedQty = \App\Support\Decimal::normalize((string) $item->returnItems->sum('quantity'));
                                    $returnableQty = \App\Support\Decimal::subtract($item->quantity, $returnedQty);
                                @endphp
                                @if(\App\Support\Decimal::isPositive($returnableQty))
                                    <div class="grid grid-cols-[auto_1fr_8rem] items-center gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                                        <input type="checkbox" class="size-4" x-model="selected[{{ $item->id }}]">
                                        <div>
                                            <div class="font-semibold">{{ $item->product_name_snapshot }}</div>
                                            <div class="text-xs text-slate-500">{{ __('ui.returnable_quantity') }}: {{ \App\Support\Decimal::display($returnableQty) }} {{ $item->unit_name_snapshot }}</div>
                                        </div>
                                        <div>
                                            <input type="hidden" name="items[{{ $item->id }}][sale_item_id]" value="{{ $item->id }}" :disabled="!selected[{{ $item->id }}]">
                                            <input class="field" name="items[{{ $item->id }}][quantity]" value="{{ \App\Support\Decimal::display($returnableQty) }}" inputmode="decimal" :disabled="!selected[{{ $item->id }}]">
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.reason') }}</label>
                            <input class="field" name="reason" required maxlength="500">
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.refund_method_if_due') }}</label>
                            <select class="field" name="refund_payment_method_id">
                                <option value="">{{ __('ui.no_refund_method') }}</option>
                                @foreach($paymentMethods as $method)
                                    <option value="{{ $method->id }}">{{ $method->localizedName() }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500">{{ __('ui.refund_method_help') }}</p>
                        </div>

                        <button class="btn-secondary w-full" type="submit">{{ __('ui.post_return') }}</button>
                    </form>
                </section>
            @endif

            @if(auth()->user()->hasPermission('sales.void'))
                <section class="panel p-5">
                    <h3 class="text-lg font-black">{{ __('ui.void_remaining_sale') }}</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ __('ui.void_sale_help') }}</p>

                    <form method="POST" action="{{ route('sales.void',$sale) }}" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.reason') }}</label>
                            <input class="field" name="reason" required maxlength="500">
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.refund_method_if_due') }}</label>
                            <select class="field" name="refund_payment_method_id">
                                <option value="">{{ __('ui.no_refund_method') }}</option>
                                @foreach($paymentMethods as $method)
                                    <option value="{{ $method->id }}">{{ $method->localizedName() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button class="w-full rounded-xl bg-red-600 px-4 py-3 text-sm font-bold text-white hover:bg-red-700" type="submit">{{ __('ui.void_remaining_sale') }}</button>
                    </form>
                </section>
            @endif
        </div>
    @endif

    @if(\App\Support\Decimal::isPositive($sale->balance_due))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
            {{ __('ui.sale_balance_due_notice') }}
        </div>
    @endif
</div>
@endsection
