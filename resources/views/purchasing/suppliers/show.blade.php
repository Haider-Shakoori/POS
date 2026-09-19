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

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.opening_balance') }}</div><div class="mt-2 text-2xl font-black">{{ \App\Support\Money::format($supplier->opening_balance) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.purchase_orders') }}</div><div class="mt-2 text-2xl font-black">{{ $supplier->purchase_orders_count }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.goods_receipts') }}</div><div class="mt-2 text-2xl font-black">{{ $supplier->goods_receipts_count }}</div></div>
    </div>

    <div class="grid gap-5 xl:grid-cols-2">
        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.recent_purchase_orders') }}</h3></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($supplier->purchaseOrders as $order)
                    <a href="{{ route('purchasing.orders.show', $order) }}" class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <div><div class="font-semibold">{{ $order->number }}</div><div class="text-xs text-slate-500">{{ $order->order_date->format('Y-m-d') }}</div></div>
                        <div class="text-end"><div class="font-bold">{{ \App\Support\Money::format($order->net_total) }}</div><div class="text-xs text-slate-500">{{ __('ui.status_'.$order->status->value) }}</div></div>
                    </a>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-400">{{ __('ui.no_purchase_orders') }}</div>
                @endforelse
            </div>
        </section>

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
    </div>
</div>
@endsection
