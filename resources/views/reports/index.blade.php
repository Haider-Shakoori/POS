@extends('layouts.app')

@section('title', __('ui.reports'))
@section('page-title', __('ui.reports'))

@section('content')
@php
    $summary = $report['summary'];
    $filters = $report['filters'];
    $money = fn ($value) => \App\Support\Money::format((string) $value);
    $locale = app()->getLocale();
    $productName = fn ($row) => ($locale === 'fa' ? ($row->name_fa ?? null) : ($locale === 'ps' ? ($row->name_ps ?? null) : null)) ?: $row->name_en;
    $categoryName = fn ($row) => ($locale === 'fa' ? ($row->name_fa ?? null) : ($locale === 'ps' ? ($row->name_ps ?? null) : null)) ?: $row->name_en;
@endphp

<div class="space-y-6">
    <div class="flex flex-col justify-between gap-4 xl:flex-row xl:items-start">
        <div>
            <h2 class="text-2xl font-black">{{ __('ui.reporting_analytics') }}</h2>
            <p class="mt-1 max-w-3xl text-sm text-slate-500">{{ __('ui.reporting_analytics_help') }}</p>
        </div>
        <a
            class="btn-secondary"
            href="{{ route('reports.sales-csv', array_filter(request()->only(['from','to','product_id','category_id','customer_id','supplier_id']))) }}"
        >
            {{ __('ui.export_sales_csv') }}
        </a>
    </div>

    <section class="panel p-5 print:hidden">
        <form method="GET" class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.from_date') }}</label>
                <input class="field" type="date" name="from" value="{{ $filters['from'] }}">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.to_date') }}</label>
                <input class="field" type="date" name="to" value="{{ $filters['to'] }}">
            </div>
            <x-async-lookup
                name="product_id"
                :label="__('ui.product')"
                :endpoint="route('reports.lookup', ['type' => 'product'])"
                :selected-value="$selectedFilters['product_id']['id'] ?? ''"
                :selected-label="$selectedFilters['product_id']['label'] ?? ''"
                :selected-meta="$selectedFilters['product_id']['meta'] ?? ''"
                :placeholder="__('ui.search_product_filter')"
            />
            <x-async-lookup
                name="category_id"
                :label="__('ui.category')"
                :endpoint="route('reports.lookup', ['type' => 'category'])"
                :selected-value="$selectedFilters['category_id']['id'] ?? ''"
                :selected-label="$selectedFilters['category_id']['label'] ?? ''"
                :selected-meta="$selectedFilters['category_id']['meta'] ?? ''"
                :placeholder="__('ui.search_category_filter')"
            />
            <x-async-lookup
                name="customer_id"
                :label="__('ui.customer')"
                :endpoint="route('reports.lookup', ['type' => 'customer'])"
                :selected-value="$selectedFilters['customer_id']['id'] ?? ''"
                :selected-label="$selectedFilters['customer_id']['label'] ?? ''"
                :selected-meta="$selectedFilters['customer_id']['meta'] ?? ''"
                :placeholder="__('ui.search_customer_filter')"
            />
            <x-async-lookup
                name="supplier_id"
                :label="__('ui.supplier')"
                :endpoint="route('reports.lookup', ['type' => 'supplier'])"
                :selected-value="$selectedFilters['supplier_id']['id'] ?? ''"
                :selected-label="$selectedFilters['supplier_id']['label'] ?? ''"
                :selected-meta="$selectedFilters['supplier_id']['meta'] ?? ''"
                :placeholder="__('ui.search_supplier_filter')"
            />
            <div class="md:col-span-2 xl:col-span-6 flex justify-end gap-2">
                <a class="btn-secondary" href="{{ route('reports.index') }}">{{ __('ui.reset') }}</a>
                <button class="btn-primary" type="submit">{{ __('ui.apply_filters') }}</button>
            </div>
        </form>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.net_sales') }}</div>
            <div class="mt-2 text-xl font-black">{{ $money($summary['net_sales']) }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ $summary['sales_count'] }} {{ __('ui.sales_count') }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.average_order_value') }}</div>
            <div class="mt-2 text-xl font-black">{{ $money($summary['aov']) }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.receivables') }}</div>
            <div class="mt-2 text-xl font-black">{{ $money($summary['receivables']) }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.supplier_payables') }}</div>
            <div class="mt-2 text-xl font-black">{{ $money($summary['payables']) }}</div>
        </div>
    </div>

    @if($canViewProfit)
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.net_cogs') }}</div><div class="mt-2 text-xl font-black">{{ $money($summary['net_cogs']) }}</div></div>
            <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.gross_profit') }}</div><div class="mt-2 text-xl font-black">{{ $money($summary['gross_profit']) }}</div></div>
            <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.operating_expenses') }}</div><div class="mt-2 text-xl font-black">{{ $money($summary['expenses']) }}</div></div>
            <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.other_income') }}</div><div class="mt-2 text-xl font-black">{{ $money($summary['other_income']) }}</div></div>
            <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.net_profit') }}</div><div class="mt-2 text-xl font-black">{{ $money($summary['net_profit']) }}</div></div>
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-2">
        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="font-black">{{ __('ui.sales_trend') }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ __('ui.sales_trend_help') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40">
                        <tr>
                            <th class="px-5 py-3 text-start">{{ __('ui.date') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('ui.sales_count') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('ui.net_sales') }}</th>
                            @if($canViewProfit)<th class="px-5 py-3 text-end">{{ __('ui.gross_profit') }}</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($report['salesTrend'] as $row)
                            <tr>
                                <td class="px-5 py-4">{{ $row->day }}</td>
                                <td class="px-5 py-4 text-end">{{ $row->sales_count }}</td>
                                <td class="px-5 py-4 text-end font-bold">{{ $money($row->net_total) }}</td>
                                @if($canViewProfit)<td class="px-5 py-4 text-end">{{ $money($row->gross_profit) }}</td>@endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canViewProfit ? 4 : 3 }}" class="px-5 py-10 text-center text-slate-400">{{ __('ui.no_report_data') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel p-5">
            <h3 class="font-black">{{ __('ui.inventory_health') }}</h3>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-950/40"><div class="text-xs text-slate-500">{{ __('ui.inventory_value') }}</div><div class="mt-1 font-black">{{ $money($summary['inventory_value']) }}</div></div>
                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-950/40"><div class="text-xs text-slate-500">{{ __('ui.tracked_products') }}</div><div class="mt-1 font-black">{{ $report['inventory']['tracked_products'] }}</div></div>
                <div class="rounded-xl bg-amber-50 p-4 dark:bg-amber-950/30"><div class="text-xs text-amber-700 dark:text-amber-300">{{ __('ui.low_stock') }}</div><div class="mt-1 font-black">{{ $report['inventory']['low_stock'] }}</div></div>
                <div class="rounded-xl bg-red-50 p-4 dark:bg-red-950/30"><div class="text-xs text-red-700 dark:text-red-300">{{ __('ui.out_of_stock') }}</div><div class="mt-1 font-black">{{ $report['inventory']['out_of_stock'] }}</div></div>
                <div class="rounded-xl bg-red-50 p-4 dark:bg-red-950/30"><div class="text-xs text-red-700 dark:text-red-300">{{ __('ui.expired_batches') }}</div><div class="mt-1 font-black">{{ $report['inventory']['expired_batches'] }}</div></div>
                <div class="rounded-xl bg-amber-50 p-4 dark:bg-amber-950/30"><div class="text-xs text-amber-700 dark:text-amber-300">{{ __('ui.expiring_batches_30') }}</div><div class="mt-1 font-black">{{ $report['inventory']['expiring_batches'] }}</div></div>
            </div>
            @if($canViewProfit)
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800"><div class="text-xs text-slate-500">{{ __('ui.damaged_cost') }}</div><div class="mt-1 font-black">{{ $money($summary['damaged_cost']) }}</div></div>
                    <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800"><div class="text-xs text-slate-500">{{ __('ui.expired_cost') }}</div><div class="mt-1 font-black">{{ $money($summary['expired_cost']) }}</div></div>
                </div>
            @endif
        </section>
    </div>

    <div class="grid gap-5 xl:grid-cols-2">
        @foreach([
            ['key' => 'topProducts', 'title' => __('ui.best_sellers')],
            ['key' => 'slowProducts', 'title' => __('ui.slow_movers')],
        ] as $section)
            <section class="panel overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ $section['title'] }}</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40">
                            <tr>
                                <th class="px-5 py-3 text-start">{{ __('ui.product') }}</th>
                                <th class="px-5 py-3 text-end">{{ __('ui.net_quantity') }}</th>
                                <th class="px-5 py-3 text-end">{{ __('ui.net_sales') }}</th>
                                @if($canViewProfit)<th class="px-5 py-3 text-end">{{ __('ui.gross_profit') }}</th>@endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse($report[$section['key']] as $row)
                                <tr>
                                    <td class="px-5 py-4"><div class="font-bold">{{ $productName($row) }}</div><div class="text-xs text-slate-500">{{ $row->sku }}</div></td>
                                    <td class="px-5 py-4 text-end">{{ $row->quantity_base }}</td>
                                    <td class="px-5 py-4 text-end">{{ $money($row->net_sales) }}</td>
                                    @if($canViewProfit)<td class="px-5 py-4 text-end font-bold">{{ $money($row->gross_profit) }}</td>@endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ $canViewProfit ? 4 : 3 }}" class="px-5 py-10 text-center text-slate-400">{{ __('ui.no_report_data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>

    @if($canViewProfit)
        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.profit_by_category') }}</h3></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40">
                        <tr>
                            <th class="px-5 py-3 text-start">{{ __('ui.category') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('ui.net_sales') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('ui.net_cogs') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('ui.gross_profit') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($report['categoryProfit'] as $row)
                            <tr>
                                <td class="px-5 py-4 font-bold">{{ $categoryName($row) }}</td>
                                <td class="px-5 py-4 text-end">{{ $money($row->net_sales) }}</td>
                                <td class="px-5 py-4 text-end">{{ $money($row->cogs) }}</td>
                                <td class="px-5 py-4 text-end font-black">{{ $money($row->gross_profit) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-slate-400">{{ __('ui.no_report_data') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <div class="grid gap-5 xl:grid-cols-2">
        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.customer_activity') }}</h3></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40"><tr><th class="px-5 py-3 text-start">{{ __('ui.customer') }}</th><th class="px-5 py-3 text-end">{{ __('ui.sales_count') }}</th><th class="px-5 py-3 text-end">{{ __('ui.sales_net_total') }}</th><th class="px-5 py-3 text-end">{{ __('ui.balance') }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($report['customers'] as $row)
                            <tr><td class="px-5 py-4"><div class="font-bold">{{ $row->name }}</div><div class="text-xs text-slate-500">{{ $row->phone }}</div></td><td class="px-5 py-4 text-end">{{ $row->sales_count }}</td><td class="px-5 py-4 text-end">{{ $money($row->sales_total) }}</td><td class="px-5 py-4 text-end">{{ $money($row->current_balance) }}</td></tr>
                        @empty<tr><td colspan="4" class="px-5 py-10 text-center text-slate-400">{{ __('ui.no_report_data') }}</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.supplier_activity') }}</h3></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40"><tr><th class="px-5 py-3 text-start">{{ __('ui.supplier') }}</th><th class="px-5 py-3 text-end">{{ __('ui.receipts') }}</th><th class="px-5 py-3 text-end">{{ __('ui.purchases') }}</th><th class="px-5 py-3 text-end">{{ __('ui.balance') }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($report['suppliers'] as $row)
                            <tr><td class="px-5 py-4"><div class="font-bold">{{ $row->name }}</div><div class="text-xs text-slate-500">{{ $row->phone }}</div></td><td class="px-5 py-4 text-end">{{ $row->receipt_count }}</td><td class="px-5 py-4 text-end">{{ $money($row->purchases_total) }}</td><td class="px-5 py-4 text-end">{{ $money($row->current_balance) }}</td></tr>
                        @empty<tr><td colspan="4" class="px-5 py-10 text-center text-slate-400">{{ __('ui.no_report_data') }}</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="grid gap-5 xl:grid-cols-2">
        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.expiry_watch') }}</h3></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40"><tr><th class="px-5 py-3 text-start">{{ __('ui.product') }}</th><th class="px-5 py-3 text-start">{{ __('ui.batch_number') }}</th><th class="px-5 py-3 text-end">{{ __('ui.stock') }}</th><th class="px-5 py-3 text-start">{{ __('ui.expiry_date') }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($report['expiring'] as $batch)
                            <tr><td class="px-5 py-4 font-bold">{{ $batch->product->localizedName() }}</td><td class="px-5 py-4">{{ $batch->batch_number }}</td><td class="px-5 py-4 text-end">{{ $batch->stock_on_hand }} {{ $batch->product->baseUnit?->symbol }}</td><td class="px-5 py-4">{{ $batch->expires_at?->format('Y-m-d') }}</td></tr>
                        @empty<tr><td colspan="4" class="px-5 py-10 text-center text-slate-400">{{ __('ui.no_report_data') }}</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.daily_closing_history') }}</h3></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40"><tr><th class="px-5 py-3 text-start">{{ __('ui.business_date') }}</th><th class="px-5 py-3 text-start">{{ __('ui.document_number') }}</th><th class="px-5 py-3 text-end">{{ __('ui.net_sales') }}</th><th class="px-5 py-3 text-end">{{ __('ui.variance') }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($report['closingHistory'] as $closure)
                            <tr><td class="px-5 py-4">{{ $closure->businessDay->business_date->format('Y-m-d') }}</td><td class="px-5 py-4 font-bold">{{ $closure->number }}</td><td class="px-5 py-4 text-end">{{ $money($closure->net_sales_total) }}</td><td class="px-5 py-4 text-end">{{ $money($closure->variance_total) }}</td></tr>
                        @empty<tr><td colspan="4" class="px-5 py-10 text-center text-slate-400">{{ __('ui.no_report_data') }}</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="grid gap-5 xl:grid-cols-2">
        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.peak_hours') }}</h3></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40"><tr><th class="px-5 py-3 text-start">{{ __('ui.hour') }}</th><th class="px-5 py-3 text-end">{{ __('ui.sales_count') }}</th><th class="px-5 py-3 text-end">{{ __('ui.net_sales') }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($report['peakHours'] as $row)
                            <tr><td class="px-5 py-4">{{ str_pad((string) $row->hour, 2, '0', STR_PAD_LEFT) }}:00</td><td class="px-5 py-4 text-end">{{ $row->sales_count }}</td><td class="px-5 py-4 text-end">{{ $money($row->net_total) }}</td></tr>
                        @empty<tr><td colspan="3" class="px-5 py-10 text-center text-slate-400">{{ __('ui.no_report_data') }}</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.weekday_performance') }}</h3></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40"><tr><th class="px-5 py-3 text-start">{{ __('ui.weekday') }}</th><th class="px-5 py-3 text-end">{{ __('ui.sales_count') }}</th><th class="px-5 py-3 text-end">{{ __('ui.net_sales') }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @php($weekdays = [__('ui.monday'),__('ui.tuesday'),__('ui.wednesday'),__('ui.thursday'),__('ui.friday'),__('ui.saturday'),__('ui.sunday')])
                        @forelse($report['weekdays'] as $row)
                            <tr><td class="px-5 py-4">{{ $weekdays[(int) $row->weekday_index] ?? $row->weekday_index }}</td><td class="px-5 py-4 text-end">{{ $row->sales_count }}</td><td class="px-5 py-4 text-end">{{ $money($row->net_total) }}</td></tr>
                        @empty<tr><td colspan="3" class="px-5 py-10 text-center text-slate-400">{{ __('ui.no_report_data') }}</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="panel p-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div><div class="text-xs text-slate-500">{{ __('ui.purchases') }}</div><div class="font-black">{{ $money($summary['purchases']) }}</div></div>
            <div><div class="text-xs text-slate-500">{{ __('ui.purchase_returns') }}</div><div class="font-black">{{ $money($summary['purchase_returns']) }}</div></div>
            <div><div class="text-xs text-slate-500">{{ __('ui.customer_collections') }}</div><div class="font-black">{{ $money($summary['collections']) }}</div></div>
            <div><div class="text-xs text-slate-500">{{ __('ui.supplier_payments') }}</div><div class="font-black">{{ $money($summary['supplier_payments']) }}</div></div>
        </div>
    </section>
</div>
@endsection
