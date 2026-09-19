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
        <div class="flex gap-2">
            <a href="{{ route('purchasing.suppliers.show', $receipt->supplier) }}" class="btn-secondary">{{ __('ui.supplier_ledger') }}</a>
            <a href="{{ route('purchasing.receipts.index') }}" class="btn-secondary">{{ __('ui.back') }}</a>
        </div>
    </div>

    @error('purchase_return')
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</div>
    @enderror

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.net_total') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($receipt->net_total) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.paid_amount') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($receipt->paid_amount) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.returned_total') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($receipt->returned_total) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.balance_due') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($receipt->balance_due) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.supplier_payable') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($receipt->supplier->current_balance) }}</div></div>
    </div>

    <section class="panel overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="font-black">{{ __('ui.received_items') }}</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ __('ui.purchase_returnable_help') }}</p>
                </div>
                <div class="text-sm font-semibold">{{ __('ui.purchase_expenses') }}: {{ \App\Support\Money::format($receipt->expense_total) }}</div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40">
                    <tr>
                        <th class="px-5 py-3 text-start">{{ __('ui.product') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.unit') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.quantity') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.returned_quantity') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.returnable_quantity') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.landed_total') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.batch') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($receipt->items as $item)
                        <tr>
                            <td class="px-5 py-4"><div class="font-semibold">{{ $item->product->localizedName() }}</div><div class="text-xs text-slate-500">{{ $item->product->sku }}</div></td>
                            <td class="px-5 py-4">{{ $item->productUnit->unit->localizedName() }}</td>
                            <td class="px-5 py-4 text-end">{{ \App\Support\Decimal::display($item->quantity) }}</td>
                            <td class="px-5 py-4 text-end">{{ \App\Support\Decimal::display($returnedQuantities[$item->id]) }}</td>
                            <td class="px-5 py-4 text-end font-bold">{{ \App\Support\Decimal::display($returnableQuantities[$item->id]) }}</td>
                            <td class="px-5 py-4 text-end font-bold">{{ \App\Support\Money::format($item->landed_total) }}</td>
                            <td class="px-5 py-4">@if($item->batch_number)<div class="font-mono">{{ $item->batch_number }}</div><div class="text-xs text-slate-500">{{ $item->expires_at?->format('Y-m-d') }}</div>@else — @endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-2">
        <section class="panel p-5">
            <h3 class="font-black">{{ __('ui.payment_history') }}</h3>
            <div class="mt-4 space-y-3">
                @foreach($receipt->payments as $payment)
                    <div class="rounded-xl bg-slate-50 px-3 py-3 text-sm dark:bg-slate-800/60">
                        <div class="flex justify-between"><span>{{ __('ui.initial_payment') }} · {{ __('ui.payment_'.$payment->method->value) }}</span><strong>{{ \App\Support\Money::format($payment->amount) }}</strong></div>
                        @if($payment->reference)<div class="mt-1 text-xs text-slate-500">{{ $payment->reference }}</div>@endif
                    </div>
                @endforeach

                @foreach($receipt->supplierPaymentAllocations as $allocation)
                    <div class="rounded-xl bg-slate-50 px-3 py-3 text-sm dark:bg-slate-800/60">
                        <div class="flex justify-between"><span>{{ $allocation->payment->number }} · {{ __('ui.payment_'.$allocation->payment->method->value) }}</span><strong>{{ \App\Support\Money::format($allocation->amount) }}</strong></div>
                        <div class="mt-1 text-xs text-slate-500">{{ $allocation->payment->paid_at->format('Y-m-d H:i') }}</div>
                    </div>
                @endforeach

                @if($receipt->payments->isEmpty() && $receipt->supplierPaymentAllocations->isEmpty())
                    <div class="text-sm text-slate-400">{{ __('ui.no_payments') }}</div>
                @endif
            </div>
        </section>

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
    </div>

    @if($receipt->purchaseReturns->isNotEmpty())
        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.purchase_returns') }}</h3></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($receipt->purchaseReturns->sortByDesc('posted_at') as $return)
                    <div class="px-5 py-4 text-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div><div class="font-bold">{{ $return->number }}</div><div class="mt-1 text-xs text-slate-500">{{ $return->posted_at->format('Y-m-d H:i') }} · {{ $return->reason }}</div></div>
                            <div class="font-black">{{ \App\Support\Money::format($return->return_total) }}</div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-500">
                            @foreach($return->items as $returnItem)
                                <span class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800">{{ $returnItem->goodsReceiptItem->product->localizedName() ?? ('#'.$returnItem->goods_receipt_item_id) }} × {{ \App\Support\Decimal::display($returnItem->quantity) }}</span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @php
        $hasReturnable = collect($returnableQuantities)->contains(fn ($quantity) => \App\Support\Decimal::isPositive($quantity));
    @endphp

    @if($hasReturnable && auth()->user()->hasPermission('purchases.return'))
        <section class="panel p-5" x-data="{ selected: {} }">
            <h3 class="text-lg font-black">{{ __('ui.create_purchase_return') }}</h3>
            <p class="mt-1 text-xs text-slate-500">{{ __('ui.create_purchase_return_help') }}</p>

            <form method="POST" action="{{ route('purchasing.receipts.returns.store', $receipt) }}" class="mt-5 space-y-4">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                <div class="space-y-2">
                    @foreach($receipt->items as $item)
                        @if(\App\Support\Decimal::isPositive($returnableQuantities[$item->id]))
                            <div class="grid grid-cols-[auto_1fr_9rem] items-center gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                                <input type="checkbox" class="size-4" x-model="selected[{{ $item->id }}]">
                                <div>
                                    <div class="font-semibold">{{ $item->product->localizedName() }}</div>
                                    <div class="text-xs text-slate-500">{{ __('ui.returnable_quantity') }}: {{ \App\Support\Decimal::display($returnableQuantities[$item->id]) }} {{ $item->productUnit->unit->localizedName() }}</div>
                                </div>
                                <div>
                                    <input type="hidden" name="items[{{ $item->id }}][goods_receipt_item_id]" value="{{ $item->id }}" :disabled="!selected[{{ $item->id }}]">
                                    <input class="field" name="items[{{ $item->id }}][quantity]" value="{{ \App\Support\Decimal::display($returnableQuantities[$item->id]) }}" inputmode="decimal" :disabled="!selected[{{ $item->id }}]">
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.reason') }}</label>
                    <input class="field" name="reason" value="{{ old('reason') }}" maxlength="500" required>
                </div>

                <button class="btn-primary" type="submit">{{ __('ui.post_purchase_return') }}</button>
            </form>
        </section>
    @endif

    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
        {{ __('ui.receipt_stock_posted_notice') }}
    </div>
</div>
@endsection
