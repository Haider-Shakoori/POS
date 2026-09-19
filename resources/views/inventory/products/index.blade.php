@extends('layouts.app')

@section('title', __('ui.products'))
@section('page-title', __('ui.products'))

@section('content')
<div class="space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-black">{{ __('ui.product_catalog') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('ui.product_catalog_help') }}</p>
        </div>
        <div class="flex gap-2">
            @if(auth()->user()->hasPermission('inventory.catalog.manage'))
                <a href="{{ route('inventory.catalog.index') }}" class="btn-secondary">{{ __('ui.catalog_setup') }}</a>
            @endif
            @if(auth()->user()->hasPermission('inventory.products.manage'))
                <a href="{{ route('inventory.products.create') }}" class="btn-primary">{{ __('ui.add_product') }}</a>
            @endif
        </div>
    </div>

    <form method="GET" class="panel grid gap-3 p-4 md:grid-cols-[minmax(0,1fr)_14rem_10rem_auto]">
        <input class="field" name="q" value="{{ request('q') }}" placeholder="{{ __('ui.search_products') }}">
        <select class="field" name="category_id">
            <option value="">{{ __('ui.all_categories') }}</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->localizedName() }}</option>
            @endforeach
        </select>
        <select class="field" name="status">
            <option value="">{{ __('ui.all_statuses') }}</option>
            <option value="active" @selected(request('status') === 'active')>{{ __('ui.active') }}</option>
            <option value="inactive" @selected(request('status') === 'inactive')>{{ __('ui.inactive') }}</option>
        </select>
        <button class="btn-secondary" type="submit">{{ __('ui.filter') }}</button>
    </form>

    <div class="panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-950/40">
                    <tr>
                        <th class="px-5 py-3 text-start">{{ __('ui.product') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.sku') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.category') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.stock') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.sale_price') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('ui.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($products as $product)
                        <tr class="transition hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                            <td class="px-5 py-4">
                                <a href="{{ route('inventory.products.show', $product) }}" class="font-bold text-slate-950 hover:text-brand-600 dark:text-white">{{ $product->localizedName() }}</a>
                                <div class="mt-1 text-xs text-slate-500">{{ $product->barcodes_count }} {{ __('ui.barcodes') }}</div>
                            </td>
                            <td class="px-5 py-4 font-mono text-xs">{{ $product->sku }}</td>
                            <td class="px-5 py-4">{{ $product->category?->localizedName() ?? '—' }}</td>
                            <td class="px-5 py-4 text-end font-semibold">{{ \App\Support\Decimal::display($product->stock_on_hand) }} {{ $product->baseUnit?->symbol }}</td>
                            <td class="px-5 py-4 text-end font-semibold">{{ \App\Support\Money::format($product->selling_price) }}</td>
                            <td class="px-5 py-4 text-center">
                                <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $product->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">
                                    {{ $product->is_active ? __('ui.active') : __('ui.inactive') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-16 text-center text-slate-500">{{ __('ui.no_products') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $products->links() }}</div>
    </div>
</div>
@endsection
