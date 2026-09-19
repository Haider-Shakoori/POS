@extends('layouts.app')

@section('title', __('ui.purchase_orders'))
@section('page-title', __('ui.purchase_orders'))

@section('content')
<div class="space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div><h2 class="text-2xl font-black">{{ __('ui.purchase_orders') }}</h2><p class="mt-1 text-sm text-slate-500">{{ __('ui.purchase_orders_help') }}</p></div>
        @if(auth()->user()->hasPermission('purchases.create'))
            <a href="{{ route('purchasing.orders.create') }}" class="btn-primary">{{ __('ui.new_purchase_order') }}</a>
        @endif
    </div>

    <form method="GET" class="panel grid gap-3 p-4 md:grid-cols-[minmax(0,1fr)_13rem_auto]">
        <input class="field" name="q" value="{{ request('q') }}" placeholder="{{ __('ui.search_purchase_orders') }}">
        <select class="field" name="status">
            <option value="">{{ __('ui.all_statuses') }}</option>
            @foreach(\App\Enums\PurchaseOrderStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ __('ui.status_'.$status->value) }}</option>
            @endforeach
        </select>
        <button class="btn-secondary" type="submit">{{ __('ui.filter') }}</button>
    </form>

    <div class="panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-950/40">
                <tr>
                    <th class="px-5 py-3 text-start">{{ __('ui.order_number') }}</th>
                    <th class="px-5 py-3 text-start">{{ __('ui.supplier') }}</th>
                    <th class="px-5 py-3 text-start">{{ __('ui.order_date') }}</th>
                    <th class="px-5 py-3 text-end">{{ __('ui.items') }}</th>
                    <th class="px-5 py-3 text-end">{{ __('ui.net_total') }}</th>
                    <th class="px-5 py-3 text-center">{{ __('ui.status') }}</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($orders as $order)
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                        <td class="px-5 py-4"><a class="font-bold hover:text-brand-600" href="{{ route('purchasing.orders.show', $order) }}">{{ $order->number }}</a></td>
                        <td class="px-5 py-4">{{ $order->supplier->name }}</td>
                        <td class="px-5 py-4">{{ $order->order_date->format('Y-m-d') }}</td>
                        <td class="px-5 py-4 text-end">{{ $order->items_count }}</td>
                        <td class="px-5 py-4 text-end font-bold">{{ \App\Support\Money::format($order->net_total) }}</td>
                        <td class="px-5 py-4 text-center"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold dark:bg-slate-800">{{ __('ui.status_'.$order->status->value) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">{{ __('ui.no_purchase_orders') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $orders->links() }}</div>
    </div>
</div>
@endsection
