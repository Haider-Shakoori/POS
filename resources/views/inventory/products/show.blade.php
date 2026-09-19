@extends('layouts.app')

@section('title', $product->localizedName())
@section('page-title', $product->localizedName())

@section('content')
<div class="space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-2xl font-black">{{ $product->localizedName() }}</h2>
                @if($product->track_expiry)<span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">{{ __('ui.expiry_tracked') }}</span>@endif
            </div>
            <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-slate-500">
                <span>{{ __('ui.sku') }}: <strong class="font-mono text-slate-700 dark:text-slate-300">{{ $product->sku }}</strong></span>
                <span>{{ __('ui.category') }}: {{ $product->category?->localizedName() ?? '—' }}</span>
                <span>{{ __('ui.brand') }}: {{ $product->brand?->localizedName() ?? '—' }}</span>
            </div>
        </div>
        <a href="{{ route('inventory.products.index') }}" class="btn-secondary">{{ __('ui.back_to_products') }}</a>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.stock_on_hand') }}</div><div class="mt-2 text-2xl font-black">{{ \App\Support\Decimal::display($product->stock_on_hand) }} <span class="text-sm font-semibold text-slate-500">{{ $product->baseUnit->symbol }}</span></div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.sale_price') }}</div><div class="mt-2 text-2xl font-black">{{ \App\Support\Money::format($product->selling_price) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.purchase_cost') }}</div><div class="mt-2 text-2xl font-black">{{ \App\Support\Money::format($product->purchase_cost) }}</div></div>
        <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.minimum_stock') }}</div><div class="mt-2 text-2xl font-black">{{ \App\Support\Decimal::display($product->minimum_stock) }}</div></div>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-5">
            <section class="panel overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.units_barcodes') }}</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40"><tr><th class="px-5 py-3 text-start">{{ __('ui.unit') }}</th><th class="px-5 py-3 text-end">{{ __('ui.conversion_factor') }}</th><th class="px-5 py-3 text-end">{{ __('ui.sale_price') }}</th><th class="px-5 py-3 text-start">{{ __('ui.barcodes') }}</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($product->productUnits as $productUnit)
                                <tr>
                                    <td class="px-5 py-4 font-semibold">{{ $productUnit->unit->localizedName() }} <span class="text-xs text-slate-400">{{ $productUnit->unit->code }}</span></td>
                                    <td class="px-5 py-4 text-end">{{ \App\Support\Decimal::display($productUnit->conversion_factor) }} × {{ $product->baseUnit->symbol }}</td>
                                    <td class="px-5 py-4 text-end">{{ $productUnit->selling_price !== null ? \App\Support\Money::format($productUnit->selling_price) : '—' }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            @forelse($product->barcodes->where('product_unit_id', $productUnit->id) as $barcode)
                                                <span class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs dark:bg-slate-800">{{ $barcode->barcode }}@if($barcode->is_primary) ★@endif</span>
                                            @empty
                                                <span class="text-slate-400">—</span>
                                            @endforelse
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            @if($product->track_expiry)
                <section class="panel overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.batches') }}</h3></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40"><tr><th class="px-5 py-3 text-start">{{ __('ui.batch_number') }}</th><th class="px-5 py-3 text-start">{{ __('ui.expiry_date') }}</th><th class="px-5 py-3 text-end">{{ __('ui.stock') }}</th></tr></thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse($product->batches as $batch)
                                    <tr><td class="px-5 py-4 font-mono">{{ $batch->batch_number }}</td><td class="px-5 py-4">{{ $batch->expires_at?->format('Y-m-d') ?? '—' }}</td><td class="px-5 py-4 text-end font-semibold">{{ \App\Support\Decimal::display($batch->stock_on_hand) }}</td></tr>
                                @empty
                                    <tr><td colspan="3" class="px-5 py-8 text-center text-slate-400">{{ __('ui.no_batches') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <section class="panel overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-black">{{ __('ui.recent_stock_movements') }}</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40"><tr><th class="px-5 py-3 text-start">{{ __('ui.date') }}</th><th class="px-5 py-3 text-start">{{ __('ui.type') }}</th><th class="px-5 py-3 text-end">{{ __('ui.quantity') }}</th><th class="px-5 py-3 text-end">{{ __('ui.balance') }}</th><th class="px-5 py-3 text-start">{{ __('ui.user') }}</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse($movements as $movement)
                                <tr><td class="px-5 py-4">{{ $movement->occurred_at->format('Y-m-d H:i') }}</td><td class="px-5 py-4">{{ str_replace('_', ' ', ucfirst($movement->movement_type->value)) }}</td><td class="px-5 py-4 text-end font-semibold">{{ \App\Support\Decimal::display($movement->quantity_base) }}</td><td class="px-5 py-4 text-end">{{ \App\Support\Decimal::display($movement->balance_after) }}</td><td class="px-5 py-4">{{ $movement->actor?->name ?? '—' }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-8 text-center text-slate-400">{{ __('ui.no_stock_movements') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="space-y-5">
            @if($product->track_stock && auth()->user()->hasPermission('inventory.opening_stock'))
                <section class="panel p-5">
                    <h3 class="font-black">{{ __('ui.record_opening_stock') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('ui.opening_stock_help') }}</p>
                    <form method="POST" action="{{ route('inventory.products.opening-stock.store', $product) }}" class="mt-5 space-y-4">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.quantity') }}</label><input class="field" name="quantity" value="{{ old('quantity') }}" inputmode="decimal" required></div>
                        <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.unit') }}</label><select class="field" name="unit_id" required>@foreach($product->productUnits as $productUnit)<option value="{{ $productUnit->unit_id }}">{{ $productUnit->unit->localizedName() }} (×{{ \App\Support\Decimal::display($productUnit->conversion_factor) }})</option>@endforeach</select></div>
                        <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.unit_cost') }}</label><input class="field" name="unit_cost" value="{{ old('unit_cost') }}" inputmode="decimal"></div>
                        @if($product->track_expiry)
                            <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.batch_number') }}</label><input class="field" name="batch_number" value="{{ old('batch_number') }}" required></div>
                            <div class="grid grid-cols-2 gap-3">
                                <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.manufactured_date') }}</label><input class="field" type="date" name="manufactured_at" value="{{ old('manufactured_at') }}"></div>
                                <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.expiry_date') }}</label><input class="field" type="date" name="expires_at" value="{{ old('expires_at') }}" required></div>
                            </div>
                        @endif
                        <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.notes') }}</label><textarea class="field" name="notes" rows="3">{{ old('notes') }}</textarea></div>
                        <button class="btn-primary w-full" type="submit">{{ __('ui.record_stock') }}</button>
                    </form>
                </section>
            @endif

            <section class="panel p-5">
                <h3 class="font-black">{{ __('ui.stock_integrity') }}</h3>
                <p class="mt-3 text-sm leading-6 text-slate-500">{{ __('ui.stock_integrity_help') }}</p>
            </section>
        </aside>
    </div>
</div>
@endsection
