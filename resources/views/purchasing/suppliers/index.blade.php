@extends('layouts.app')

@section('title', __('ui.suppliers'))
@section('page-title', __('ui.suppliers'))

@section('content')
<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_23rem]">
    <section class="space-y-5">
        <div>
            <h2 class="text-2xl font-black">{{ __('ui.suppliers') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('ui.suppliers_help') }}</p>
        </div>

        <form method="GET" class="panel flex gap-3 p-4">
            <input class="field" name="q" value="{{ request('q') }}" placeholder="{{ __('ui.search_suppliers') }}">
            <button class="btn-secondary" type="submit">{{ __('ui.search') }}</button>
        </form>

        <div class="panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-950/40">
                    <tr>
                        <th class="px-5 py-3 text-start">{{ __('ui.supplier') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.phone') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.purchase_orders') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.goods_receipts') }}</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($suppliers as $supplier)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                            <td class="px-5 py-4">
                                <a class="font-bold hover:text-brand-600" href="{{ route('purchasing.suppliers.show', $supplier) }}">{{ $supplier->name }}</a>
                                <div class="mt-1 text-xs text-slate-500">{{ $supplier->contact_person ?: '—' }}</div>
                            </td>
                            <td class="px-5 py-4">{{ $supplier->phone ?: '—' }}</td>
                            <td class="px-5 py-4 text-end font-semibold">{{ $supplier->purchase_orders_count }}</td>
                            <td class="px-5 py-4 text-end font-semibold">{{ $supplier->goods_receipts_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-12 text-center text-slate-400">{{ __('ui.no_suppliers') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $suppliers->links() }}</div>
        </div>
    </section>

    @if(auth()->user()->hasPermission('suppliers.manage'))
        <aside class="panel h-fit p-5">
            <h3 class="text-lg font-black">{{ __('ui.add_supplier') }}</h3>
            <form method="POST" action="{{ route('purchasing.suppliers.store') }}" class="mt-5 space-y-4">
                @csrf
                <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.supplier_name') }}</label><input class="field" name="name" value="{{ old('name') }}" required></div>
                <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.contact_person') }}</label><input class="field" name="contact_person" value="{{ old('contact_person') }}"></div>
                <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.phone') }}</label><input class="field" name="phone" value="{{ old('phone') }}"></div>
                <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.alternate_phone') }}</label><input class="field" name="alternate_phone" value="{{ old('alternate_phone') }}"></div>
                <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.opening_balance') }}</label><input class="field" name="opening_balance" value="{{ old('opening_balance', '0.00') }}" inputmode="decimal"></div>
                <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.address') }}</label><textarea class="field" name="address" rows="3">{{ old('address') }}</textarea></div>
                <button class="btn-primary w-full" type="submit">{{ __('ui.create_supplier') }}</button>
            </form>
        </aside>
    @endif
</div>
@endsection
