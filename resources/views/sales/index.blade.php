@extends('layouts.app')

@section('title', __('ui.sales'))
@section('page-title', __('ui.sales'))

@section('content')
<div class="space-y-5">
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
        <div>
            <h2 class="text-2xl font-black">{{ __('ui.sales_history') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('ui.sales_history_help') }}</p>
        </div>
        <form method="GET" class="flex w-full gap-2 lg:max-w-md">
            <input class="field" name="q" value="{{ $search }}" placeholder="{{ __('ui.search_sale') }}">
            <button class="btn-secondary" type="submit">{{ __('ui.search') }}</button>
        </form>
    </div>

    <section class="panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40">
                    <tr>
                        <th class="px-5 py-3 text-start">{{ __('ui.sale') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.customer') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.status') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.net_total') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.balance_due') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($sales as $sale)
                        <tr>
                            <td class="px-5 py-4"><a class="font-bold text-brand-700 hover:underline dark:text-brand-300" href="{{ route('sales.show',$sale) }}">{{ $sale->number }}</a></td>
                            <td class="px-5 py-4">{{ $sale->customer_name_snapshot }}</td>
                            <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ __('ui.sale_status_'.$sale->status->value) }}</span></td>
                            <td class="px-5 py-4 text-end font-bold">{{ \App\Support\Money::format($sale->net_total) }}</td>
                            <td class="px-5 py-4 text-end">{{ \App\Support\Money::format($sale->balance_due) }}</td>
                            <td class="px-5 py-4">{{ $sale->sold_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">{{ __('ui.no_sales') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $sales->links() }}</div>
    </section>
</div>
@endsection
