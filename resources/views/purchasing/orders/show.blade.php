@extends('layouts.app')

@section('title', $order->number)
@section('page-title', $order->number)

@section('content')
<div class="space-y-5">
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-2xl font-black">{{ $order->number }}</h2>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold dark:bg-slate-800">{{ __('ui.status_'.$order->status->value) }}</span>
            </div>
            <div class="mt-2 text-sm text-slate-500">{{ $order->supplier->name }} · {{ $order->order_date->format('Y-m-d') }}</div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($order->isReceivable() && auth()->user()->hasPermission('purchases.receive'))
                <a href="{{ route('purchasing.receipts.create-for-order', $order) }}" class="btn-primary">{{ __('ui.receive_goods') }}</a>
            @endif
            @if($order->status === \App\Enums\PurchaseOrderStatus::Draft && auth()->user()->hasPermission('purchases.approve'))
                <form method="POST" action="{{ route('purchasing.orders.approve', $order) }}">@csrf<button class="btn-primary" type="submit">{{ __('ui.approve') }}</button></form>
                <form method="POST" action="{{ route('purchasing.orders.cancel', $order) }}">@csrf<button class="btn-secondary" type="submit">{{ __('ui.cancel_order') }}</button></form>
            @endif
            <a href="{{ route('purchasing.orders.index') }}" class="btn-secondary">{{ __('ui.back') }}</a>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.subtotal') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($order->subtotal) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.line_discounts') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($order->line_discount_total) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.order_discount') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($order->order_discount_amount) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.net_total') }}</div><div class="mt-2 text-xl font-black">{{ \App\Support\Money::format($order->net_total) }}</div></div>
    </div>

    <section class="panel overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.order_items') }}</h3></div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40"><tr><th class="px-5 py-3 text-start">{{ __('ui.product') }}</th><th class="px-5 py-3 text-start">{{ __('ui.unit') }}</th><th class="px-5 py-3 text-end">{{ __('ui.ordered') }}</th><th class="px-5 py-3 text-end">{{ __('ui.received') }}</th><th class="px-5 py-3 text-end">{{ __('ui.remaining') }}</th><th class="px-5 py-3 text-end">{{ __('ui.unit_cost') }}</th><th class="px-5 py-3 text-end">{{ __('ui.line_total') }}</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($order->items as $item)
                    <tr>
                        <td class="px-5 py-4 font-semibold">{{ $item->product->localizedName() }}</td>
                        <td class="px-5 py-4">{{ $item->productUnit->unit->localizedName() }}</td>
                        <td class="px-5 py-4 text-end">{{ \App\Support\Decimal::display($item->ordered_quantity) }}</td>
                        <td class="px-5 py-4 text-end">{{ \App\Support\Decimal::display($item->received_quantity) }}</td>
                        <td class="px-5 py-4 text-end font-bold">{{ \App\Support\Decimal::display(\App\Support\Decimal::subtract($item->ordered_quantity,$item->received_quantity)) }}</td>
                        <td class="px-5 py-4 text-end">{{ \App\Support\Money::format(\App\Support\Decimal::round($item->unit_cost,2)) }}</td>
                        <td class="px-5 py-4 text-end font-bold">{{ \App\Support\Money::format($item->line_net_total) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.goods_receipts') }}</h3></div>
        <div class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse($order->goodsReceipts as $receipt)
                <a href="{{ route('purchasing.receipts.show',$receipt) }}" class="flex items-center justify-between px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/40"><div><div class="font-semibold">{{ $receipt->number }}</div><div class="text-xs text-slate-500">{{ $receipt->received_at->format('Y-m-d H:i') }}</div></div><div class="font-bold">{{ \App\Support\Money::format($receipt->net_total) }}</div></a>
            @empty
                <div class="px-5 py-8 text-center text-sm text-slate-400">{{ __('ui.no_goods_receipts') }}</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
