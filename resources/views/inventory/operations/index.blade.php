@extends('layouts.app')

@section('title', __('ui.inventory_operations'))
@section('page-title', __('ui.inventory_operations'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
        <div>
            <h2 class="text-2xl font-black">{{ __('ui.inventory_operations') }}</h2>
            <p class="mt-1 max-w-3xl text-sm text-slate-500">{{ __('ui.inventory_operations_help') }}</p>
        </div>
        <a class="btn-secondary" href="{{ route('inventory.products.index') }}">{{ __('ui.view_products') }}</a>
    </div>

    @error('stock_count')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</div>
    @enderror
    @error('stock_count_approval')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</div>
    @enderror
    @error('inventory_writeoff')
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</div>
    @enderror

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.expired_batches') }}</div>
            <div class="mt-2 text-2xl font-black">{{ $expiredBatchCount }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.expiring_within_days', ['days' => $expiryDays]) }}</div>
            <div class="mt-2 text-2xl font-black">{{ $expiringBatchCount }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.reorder_suggestions') }}</div>
            <div class="mt-2 text-2xl font-black">{{ $reorderSuggestionCount }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.pending_stock_counts') }}</div>
            <div class="mt-2 text-2xl font-black">{{ $pendingStockCount }}</div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        @if(auth()->user()->hasPermission('inventory.count'))
            <section class="panel p-5" x-data="inventoryTargetPicker(@js($targetSearchUrl), 'count')">
                <h3 class="text-lg font-black">{{ __('ui.new_stock_count') }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ __('ui.stock_count_help') }}</p>

                <form method="POST" action="{{ route('inventory.stock-counts.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                    <div class="relative" @click.outside="open = false">
                        <label class="field-label">{{ __('ui.search_inventory_target') }}</label>
                        <div class="relative">
                            <input
                                class="field pe-10"
                                type="search"
                                x-model="query"
                                @input.debounce.250ms="search()"
                                @focus="query.trim().length >= 2 && search()"
                                placeholder="{{ __('ui.search_inventory_target_placeholder') }}"
                                autocomplete="off"
                            >
                            <div class="pointer-events-none absolute inset-y-0 end-3 flex items-center">
                                <svg x-show="loading" class="size-4 animate-spin text-slate-400" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle>
                                    <path class="opacity-75" d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400">{{ __('ui.async_inventory_search_help') }}</p>

                        <div x-cloak x-show="open" class="absolute z-40 mt-2 max-h-72 w-full overflow-y-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                            <div x-show="!loading && results.length === 0" class="px-3 py-3 text-sm text-slate-400">
                                <span x-show="query.trim().length < 2">{{ __('ui.type_two_characters') }}</span>
                                <span x-show="query.trim().length >= 2">{{ __('ui.no_matches_found') }}</span>
                            </div>
                            <template x-for="item in results" :key="item.value">
                                <button type="button" class="flex w-full items-center justify-between gap-4 rounded-xl px-3 py-2.5 text-start transition hover:bg-slate-50 dark:hover:bg-slate-800" @click="add(item)">
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold" x-text="item.label"></span>
                                        <span class="mt-0.5 block truncate text-xs text-slate-400">
                                            <span x-text="item.meta || ''"></span>
                                            <span x-show="item.expires_at"> · {{ __('ui.expiry_date') }} <span x-text="item.expires_at"></span></span>
                                        </span>
                                    </span>
                                    <span class="shrink-0 text-end">
                                        <span class="block text-sm font-black" x-text="item.stock + (item.unit ? ' ' + item.unit : '')"></span>
                                        <span class="text-[11px] font-bold text-brand-600 dark:text-brand-300">{{ __('ui.add') }}</span>
                                    </span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(row, index) in rows" :key="row.value">
                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <input type="hidden" :name="'items['+index+'][target]'" :value="row.value">
                                <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem_auto]">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-bold" x-text="row.label"></div>
                                        <div class="mt-1 text-xs text-slate-400">
                                            {{ __('ui.system_expected') }}:
                                            <span x-text="row.stock"></span>
                                            <span x-text="row.unit || ''"></span>
                                            <span x-show="row.expires_at"> · {{ __('ui.expiry_date') }} <span x-text="row.expires_at"></span></span>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="field-label">{{ __('ui.physical_quantity') }}</label>
                                        <input class="field" :name="'items['+index+'][physical_quantity_base]'" x-model="row.quantity" inputmode="decimal" required>
                                    </div>
                                    <div class="flex items-end">
                                        <button class="btn-secondary px-3" type="button" @click="remove(index)" aria-label="{{ __('ui.remove') }}">×</button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div x-show="rows.length === 0" class="rounded-2xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-400 dark:border-slate-700">
                            {{ __('ui.no_targets_selected') }}
                        </div>
                    </div>

                    <div>
                        <label class="field-label">{{ __('ui.notes') }}</label>
                        <textarea class="field min-h-20" name="notes">{{ old('notes') }}</textarea>
                    </div>

                    <button class="btn-primary w-full" type="submit" :disabled="rows.length === 0">{{ __('ui.save_count_draft') }}</button>
                </form>
            </section>
        @endif

        @if(auth()->user()->hasPermission('inventory.writeoff'))
            <section class="panel p-5" x-data="inventoryTargetPicker(@js($targetSearchUrl), 'damage')">
                <h3 class="text-lg font-black">{{ __('ui.inventory_writeoff') }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ __('ui.inventory_writeoff_help') }}</p>

                <form method="POST" action="{{ route('inventory.writeoffs.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                    <div>
                        <label class="field-label">{{ __('ui.writeoff_type') }}</label>
                        <select class="field" name="writeoff_type" x-model="mode" @change="setMode($event.target.value)">
                            <option value="damage">{{ __('ui.damaged_stock') }}</option>
                            <option value="expiry">{{ __('ui.expired_stock') }}</option>
                        </select>
                    </div>

                    <div class="relative" @click.outside="open = false">
                        <label class="field-label">{{ __('ui.search_inventory_target') }}</label>
                        <div class="relative">
                            <input
                                class="field pe-10"
                                type="search"
                                x-model="query"
                                @input.debounce.250ms="search()"
                                @focus="query.trim().length >= 2 && search()"
                                placeholder="{{ __('ui.search_inventory_target_placeholder') }}"
                                autocomplete="off"
                            >
                            <div class="pointer-events-none absolute inset-y-0 end-3 flex items-center">
                                <svg x-show="loading" class="size-4 animate-spin text-slate-400" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle>
                                    <path class="opacity-75" d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400">{{ __('ui.async_inventory_search_help') }}</p>

                        <div x-cloak x-show="open" class="absolute z-40 mt-2 max-h-72 w-full overflow-y-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                            <div x-show="!loading && results.length === 0" class="px-3 py-3 text-sm text-slate-400">
                                <span x-show="query.trim().length < 2">{{ __('ui.type_two_characters') }}</span>
                                <span x-show="query.trim().length >= 2">{{ __('ui.no_matches_found') }}</span>
                            </div>
                            <template x-for="item in results" :key="item.value">
                                <button type="button" class="flex w-full items-center justify-between gap-4 rounded-xl px-3 py-2.5 text-start transition hover:bg-slate-50 dark:hover:bg-slate-800" @click="add(item)">
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold" x-text="item.label"></span>
                                        <span class="mt-0.5 block truncate text-xs text-slate-400">
                                            <span x-text="item.meta || ''"></span>
                                            <span x-show="item.expires_at"> · {{ __('ui.expiry_date') }} <span x-text="item.expires_at"></span></span>
                                        </span>
                                    </span>
                                    <span class="shrink-0 text-end">
                                        <span class="block text-sm font-black" x-text="item.stock + (item.unit ? ' ' + item.unit : '')"></span>
                                        <span class="text-[11px] font-bold text-brand-600 dark:text-brand-300">{{ __('ui.add') }}</span>
                                    </span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(row, index) in rows" :key="row.value">
                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <input type="hidden" :name="'items['+index+'][target]'" :value="row.value">
                                <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem_auto]">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-bold" x-text="row.label"></div>
                                        <div class="mt-1 text-xs text-slate-400">
                                            {{ __('ui.available_stock') }}:
                                            <span x-text="row.stock"></span>
                                            <span x-text="row.unit || ''"></span>
                                            <span x-show="row.expires_at"> · {{ __('ui.expiry_date') }} <span x-text="row.expires_at"></span></span>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="field-label">{{ __('ui.quantity') }}</label>
                                        <input class="field" :name="'items['+index+'][quantity_base]'" x-model="row.quantity" inputmode="decimal" required>
                                    </div>
                                    <div class="flex items-end">
                                        <button class="btn-secondary px-3" type="button" @click="remove(index)" aria-label="{{ __('ui.remove') }}">×</button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div x-show="rows.length === 0" class="rounded-2xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-400 dark:border-slate-700">
                            {{ __('ui.no_targets_selected') }}
                        </div>
                    </div>

                    <div>
                        <label class="field-label">{{ __('ui.reason') }}</label>
                        <input class="field" name="reason" required maxlength="500">
                    </div>
                    <div>
                        <label class="field-label">{{ __('ui.notes') }}</label>
                        <textarea class="field min-h-20" name="notes"></textarea>
                    </div>

                    <button class="btn-primary w-full" type="submit" :disabled="rows.length === 0">{{ __('ui.post_writeoff') }}</button>
                </form>
            </section>
        @endif
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="panel overflow-hidden">
            <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center dark:border-slate-800">
                <div>
                    <h3 class="font-black">{{ __('ui.expiry_monitoring') }}</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ __('ui.expiry_monitoring_help') }}</p>
                </div>
                <form method="GET" class="flex items-center gap-2">
                    <select class="field min-w-32" name="expiry_days">
                        @foreach([7, 14, 30, 60, 90, 180] as $days)
                            <option value="{{ $days }}" @selected($expiryDays === $days)>{{ $days }} {{ __('ui.days') }}</option>
                        @endforeach
                    </select>
                    <button class="btn-secondary" type="submit">{{ __('ui.apply') }}</button>
                </form>
            </div>

            <div class="p-5">
                <h4 class="text-sm font-black text-red-600 dark:text-red-300">{{ __('ui.expired_batches') }}</h4>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-xs text-slate-500">
                            <tr>
                                <th class="py-2 text-start">{{ __('ui.product') }}</th>
                                <th class="py-2 text-start">{{ __('ui.batch_number') }}</th>
                                <th class="py-2 text-start">{{ __('ui.expiry_date') }}</th>
                                <th class="py-2 text-end">{{ __('ui.stock') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse($expiredBatches as $batch)
                                <tr>
                                    <td class="py-3 font-semibold">{{ $batch->product->localizedName() }}</td>
                                    <td class="py-3">{{ $batch->batch_number }}</td>
                                    <td class="py-3 text-red-600 dark:text-red-300">{{ $batch->expires_at?->format('Y-m-d') }}</td>
                                    <td class="py-3 text-end">{{ \App\Support\Decimal::display($batch->stock_on_hand) }} {{ $batch->product->baseUnit?->symbol }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-slate-400">{{ __('ui.no_expired_stock') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($expiredBatches->hasPages())
                    <div class="mt-3">{{ $expiredBatches->links() }}</div>
                @endif

                <h4 class="mt-6 text-sm font-black text-amber-600 dark:text-amber-300">{{ __('ui.expiring_soon') }}</h4>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-xs text-slate-500">
                            <tr>
                                <th class="py-2 text-start">{{ __('ui.product') }}</th>
                                <th class="py-2 text-start">{{ __('ui.batch_number') }}</th>
                                <th class="py-2 text-start">{{ __('ui.expiry_date') }}</th>
                                <th class="py-2 text-end">{{ __('ui.stock') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse($expiringBatches as $batch)
                                <tr>
                                    <td class="py-3 font-semibold">{{ $batch->product->localizedName() }}</td>
                                    <td class="py-3">{{ $batch->batch_number }}</td>
                                    <td class="py-3 text-amber-600 dark:text-amber-300">{{ $batch->expires_at?->format('Y-m-d') }}</td>
                                    <td class="py-3 text-end">{{ \App\Support\Decimal::display($batch->stock_on_hand) }} {{ $batch->product->baseUnit?->symbol }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-slate-400">{{ __('ui.no_expiring_stock') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($expiringBatches->hasPages())
                    <div class="mt-3">{{ $expiringBatches->links() }}</div>
                @endif
            </div>
        </section>

        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="font-black">{{ __('ui.reorder_suggestions') }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ __('ui.reorder_suggestions_help') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40">
                        <tr>
                            <th class="px-5 py-3 text-start">{{ __('ui.product') }}</th>
                            <th class="px-5 py-3 text-start">{{ __('ui.status') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('ui.stock') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('ui.minimum_stock') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('ui.suggested_reorder') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($reorderSuggestions as $row)
                            @php($product = $row['product'])
                            <tr>
                                <td class="px-5 py-4">
                                    <a class="font-bold hover:text-brand-600" href="{{ route('inventory.products.show', $product) }}">{{ $product->localizedName() }}</a>
                                    <div class="mt-1 text-xs text-slate-500">{{ $product->sku }}</div>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $row['status'] === 'out' ? 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' }}">
                                        {{ $row['status'] === 'out' ? __('ui.out_of_stock') : __('ui.low_stock') }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-end">{{ \App\Support\Decimal::display($product->stock_on_hand) }}</td>
                                <td class="px-5 py-4 text-end">{{ \App\Support\Decimal::display($product->minimum_stock) }}</td>
                                <td class="px-5 py-4 text-end font-black">{{ \App\Support\Decimal::display($row['suggested_quantity']) }} {{ $product->baseUnit?->symbol }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">{{ __('ui.no_reorder_suggestions') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($reorderSuggestions->hasPages())
                <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $reorderSuggestions->links() }}</div>
            @endif
        </section>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="font-black">{{ __('ui.recent_stock_counts') }}</h3>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($recentCounts as $count)
                    <div class="p-5">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <div>
                                <div class="font-black">{{ $count->number }}</div>
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ $count->items_count }} {{ __('ui.items') }} · {{ $count->counted_at->format('Y-m-d H:i') }} · {{ $count->countedBy->name }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $count->status === 'approved' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' }}">
                                    {{ $count->status === 'approved' ? __('ui.approved') : __('ui.draft') }}
                                </span>
                                @if($count->status === 'draft' && auth()->user()->hasPermission('inventory.count.approve'))
                                    <form method="POST" action="{{ route('inventory.stock-counts.approve', $count) }}">
                                        @csrf
                                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <button class="btn-primary" type="submit">{{ __('ui.approve') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-slate-400">{{ __('ui.no_stock_counts') }}</div>
                @endforelse
            </div>
        </section>

        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="font-black">{{ __('ui.recent_writeoffs') }}</h3>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($recentWriteoffs as $writeoff)
                    <div class="p-5">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <div>
                                <div class="font-black">{{ $writeoff->number }}</div>
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ $writeoff->items_count }} {{ __('ui.items') }} · {{ $writeoff->posted_at->format('Y-m-d H:i') }} · {{ $writeoff->postedBy->name }}
                                </div>
                                <div class="mt-1 text-xs text-slate-500">{{ $writeoff->reason }}</div>
                            </div>
                            <div class="text-end">
                                <div class="text-xs font-bold uppercase tracking-wider text-slate-400">
                                    {{ $writeoff->writeoff_type === 'expiry' ? __('ui.expired_stock') : __('ui.damaged_stock') }}
                                </div>
                                <div class="mt-1 font-black">{{ \App\Support\Money::format(\App\Support\Decimal::round($writeoff->total_cost, 2)) }}</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-slate-400">{{ __('ui.no_writeoffs') }}</div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-4 text-xs leading-5 text-slate-500 dark:border-slate-800 dark:bg-slate-900">
        {{ __('ui.reorder_advisory_notice') }}
    </div>
</div>
@endsection
