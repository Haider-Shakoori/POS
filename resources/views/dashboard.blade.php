@extends('layouts.app')

@section('title', __('ui.dashboard'))
@section('page-title', __('ui.dashboard'))

@section('content')
@php
    $money = fn ($value) => \App\Support\Money::format((string) $value);
@endphp

<div class="space-y-6">
    <section class="relative overflow-hidden rounded-[2rem] bg-slate-950 px-6 py-7 text-white shadow-xl shadow-slate-950/10 sm:px-8 sm:py-9">
        <div class="absolute -end-16 -top-20 size-64 rounded-full bg-brand-500/15 blur-3xl"></div>
        <div class="absolute -bottom-20 start-1/3 size-56 rounded-full bg-cyan-400/10 blur-3xl"></div>

        <div class="relative flex flex-col justify-between gap-7 xl:flex-row xl:items-end">
            <div class="max-w-3xl">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-bold text-emerald-300 ring-1 ring-inset ring-emerald-400/20">
                        <span class="size-1.5 rounded-full bg-emerald-300"></span>
                        {{ __('ui.business_ready') }}
                    </span>
                    <span class="rounded-full bg-white/5 px-3 py-1 text-xs font-semibold text-slate-300 ring-1 ring-inset ring-white/10">{{ $currencyCode }} · {{ __('ui.no_sales_tax') }}</span>
                </div>
                <h2 class="mt-5 text-3xl font-black tracking-tight sm:text-4xl">{{ __('ui.welcome_back') }}, {{ auth()->user()->name }}</h2>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">{{ __('ui.business_ready_message') }}</p>

                <div class="mt-6 flex flex-wrap gap-3">
                    @if(auth()->user()->hasPermission('pos.access'))
                        <a href="{{ route('pos.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-brand-500/20 transition hover:bg-brand-400">
                            <x-nav-icon name="pos" class="size-4" />
                            {{ __('ui.open_pos') }}
                        </a>
                    @endif
                    @if(auth()->user()->hasPermission('cash.view'))
                        <a href="{{ route('cash.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-bold text-white ring-1 ring-inset ring-white/10 transition hover:bg-white/15">
                            <x-nav-icon name="cash" class="size-4" />
                            {{ $openShift ? __('ui.view_cash_drawer') : __('ui.open_shift') }}
                        </a>
                    @endif
                </div>
            </div>

            <div class="min-w-64 rounded-2xl bg-white/5 p-4 ring-1 ring-inset ring-white/10 backdrop-blur">
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">{{ __('ui.current_shift') }}</div>
                @if($openShift)
                    <div class="mt-2 flex items-center gap-2 text-lg font-black text-emerald-300">
                        <span class="size-2 rounded-full bg-emerald-300"></span>
                        {{ __('ui.shift_open') }}
                    </div>
                    <div class="mt-1 text-sm text-slate-300">{{ $openShift->terminal->name }} · {{ $openShift->opened_at->format('H:i') }}</div>
                    <div class="mt-3 border-t border-white/10 pt-3">
                        <div class="text-xs text-slate-400">{{ __('ui.expected_cash') }}</div>
                        <div class="mt-1 text-xl font-black">{{ $money($openShift->expected_cash ?? '0.00') }}</div>
                    </div>
                @else
                    <div class="mt-2 text-lg font-black">{{ __('ui.not_open') }}</div>
                    <p class="mt-1 text-xs leading-5 text-slate-400">{{ __('ui.no_open_shift_message') }}</p>
                @endif
            </div>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @if($canViewSales)
            <article class="metric-card">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="metric-label">{{ __('ui.today_net_sales') }}</div>
                        <div class="metric-value">{{ $money($todayNetSales ?? '0.00') }}</div>
                        <div class="metric-hint">{{ $todayTransactions }} {{ __('ui.transactions_today') }}</div>
                    </div>
                    <div class="metric-icon"><x-nav-icon name="sales" /></div>
                </div>
            </article>
        @endif

        @if($canViewCustomers)
            <article class="metric-card">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="metric-label">{{ __('ui.receivables') }}</div>
                        <div class="metric-value">{{ $money($receivables ?? '0.00') }}</div>
                        <div class="metric-hint">{{ __('ui.current_customer_balance') }}</div>
                    </div>
                    <div class="metric-icon"><x-nav-icon name="customers" /></div>
                </div>
            </article>
        @endif

        @if($canViewSuppliers)
            <article class="metric-card">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="metric-label">{{ __('ui.supplier_payables') }}</div>
                        <div class="metric-value">{{ $money($payables ?? '0.00') }}</div>
                        <div class="metric-hint">{{ __('ui.current_supplier_balance') }}</div>
                    </div>
                    <div class="metric-icon"><x-nav-icon name="supplier" /></div>
                </div>
            </article>
        @endif

        @if($canViewInventory)
            <article class="metric-card">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="metric-label">{{ __('ui.inventory_attention') }}</div>
                        <div class="metric-value">{{ $lowStockCount ?? 0 }}</div>
                        <div class="metric-hint">{{ __('ui.low_stock_items') }}</div>
                    </div>
                    <div class="metric-icon"><x-nav-icon name="inventory" /></div>
                </div>
            </article>
        @endif
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(22rem,0.65fr)]">
        @if($canViewSales)
            <section class="panel overflow-hidden">
                <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h3 class="section-heading">{{ __('ui.recent_sales') }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ __('ui.recent_sales_help') }}</p>
                    </div>
                    <a class="text-sm font-bold text-brand-700 hover:text-brand-800 dark:text-brand-300" href="{{ route('sales.index') }}">{{ __('ui.view_all') }}</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('ui.sale_number') }}</th>
                                <th>{{ __('ui.customer') }}</th>
                                <th>{{ __('ui.date') }}</th>
                                <th>{{ __('ui.status') }}</th>
                                <th class="text-end">{{ __('ui.net_total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentSales as $sale)
                                @php($saleNet = \App\Support\Decimal::subtract($sale->net_total, $sale->returned_total, 2))
                                <tr>
                                    <td><a class="font-bold text-brand-700 hover:underline dark:text-brand-300" href="{{ route('sales.show', $sale) }}">{{ $sale->number }}</a></td>
                                    <td>{{ $sale->customer?->name ?: $sale->customer_name_snapshot }}</td>
                                    <td class="whitespace-nowrap">{{ $sale->sold_at->format('Y-m-d H:i') }}</td>
                                    <td><span class="badge {{ $sale->payment_status->value === 'paid' ? 'badge-success' : ($sale->payment_status->value === 'partial' ? 'badge-warning' : 'badge-neutral') }}">{{ ucfirst($sale->payment_status->value) }}</span></td>
                                    <td class="text-end font-bold">{{ $money($saleNet) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty-state">{{ __('ui.no_recent_sales') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <div class="space-y-6">
            @if($canViewInventory)
                <section class="panel overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <div>
                            <h3 class="section-heading">{{ __('ui.inventory_attention') }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ __('ui.inventory_attention_help') }}</p>
                        </div>
                        <a class="text-sm font-bold text-brand-700 hover:text-brand-800 dark:text-brand-300" href="{{ route('inventory.operations.index') }}">{{ __('ui.view_all') }}</a>
                    </div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($lowStockProducts as $product)
                            <a href="{{ route('inventory.products.show', $product) }}" class="flex items-center justify-between gap-4 px-5 py-3.5 transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold">{{ $product->localizedName() }}</div>
                                    <div class="mt-0.5 text-xs text-slate-500">{{ $product->sku }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="text-sm font-black {{ \App\Support\Decimal::compare($product->stock_on_hand, '0') <= 0 ? 'text-rose-600 dark:text-rose-300' : 'text-amber-600 dark:text-amber-300' }}">
                                        {{ \App\Support\Decimal::display($product->stock_on_hand) }} {{ $product->baseUnit?->symbol }}
                                    </div>
                                    <div class="text-[11px] text-slate-400">{{ __('ui.minimum') }} {{ \App\Support\Decimal::display($product->minimum_stock) }}</div>
                                </div>
                            </a>
                        @empty
                            <div class="empty-state">{{ __('ui.no_low_stock_attention') }}</div>
                        @endforelse
                    </div>
                </section>
            @endif

            <section class="panel p-5">
                <h3 class="section-heading">{{ __('ui.quick_actions') }}</h3>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    @if(auth()->user()->hasPermission('inventory.products.manage'))
                        <a class="quick-action" href="{{ route('inventory.products.create') }}"><x-nav-icon name="products" /> <span>{{ __('ui.add_product') }}</span></a>
                    @endif
                    @if(auth()->user()->hasPermission('purchases.create'))
                        <a class="quick-action" href="{{ route('purchasing.orders.create') }}"><x-nav-icon name="purchase" /> <span>{{ __('ui.new_purchase_order') }}</span></a>
                    @endif
                    @if(auth()->user()->hasPermission('expenses.view'))
                        <a class="quick-action" href="{{ route('expenses.index') }}"><x-nav-icon name="expenses" /> <span>{{ __('ui.operating_entries') }}</span></a>
                    @endif
                    @if(auth()->user()->hasPermission('reports.view'))
                        <a class="quick-action" href="{{ route('reports.index') }}"><x-nav-icon name="reports" /> <span>{{ __('ui.reports') }}</span></a>
                    @endif
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
