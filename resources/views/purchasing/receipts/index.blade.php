@extends('layouts.app')

@section('title', __('ui.goods_receipts'))
@section('page-title', __('ui.goods_receipts'))

@section('content')
<div class="space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-black">{{ __('ui.goods_receipts') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('ui.goods_receipts_help') }}</p>
        </div>
        @if(auth()->user()->hasPermission('purchases.direct_receive'))
            <a href="{{ route('purchasing.receipts.create-direct') }}" class="btn-primary">{{ __('ui.direct_goods_receipt') }}</a>
        @endif
    </div>

    <form method="GET" class="panel flex gap-3 p-4">
        <input class="field" name="q" value="{{ request('q') }}" placeholder="{{ __('ui.search_goods_receipts') }}">
        <button class="btn-secondary" type="submit">{{ __('ui.search') }}</button>
    </form>

    <div class="panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-950/40">
                    <tr>
                        <th class="px-5 py-3 text-start">{{ __('ui.receipt_number') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.supplier') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.purchase_order') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.received_at') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.net_total') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.balance_due') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($receipts as $receipt)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                            <td class="px-5 py-4"><a class="font-bold hover:text-brand-600" href="{{ route('purchasing.receipts.show', $receipt) }}">{{ $receipt->number }}</a></td>
                            <td class="px-5 py-4">{{ $receipt->supplier->name }}</td>
                            <td class="px-5 py-4">{{ $receipt->purchaseOrder?->number ?? __('ui.direct_receipt') }}</td>
                            <td class="px-5 py-4">{{ $receipt->received_at->format('Y-m-d H:i') }}</td>
                            <td class="px-5 py-4 text-end font-bold">{{ \App\Support\Money::format($receipt->net_total) }}</td>
                            <td class="px-5 py-4 text-end">{{ \App\Support\Money::format($receipt->balance_due) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">{{ __('ui.no_goods_receipts') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $receipts->links() }}</div>
    </div>
</div>
@endsection
