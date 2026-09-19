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
            <div class="mt-2 text-2xl font-black">{{ $expiredBatches->count() }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.expiring_within_days', ['days' => $expiryDays]) }}</div>
            <div class="mt-2 text-2xl font-black">{{ $expiringBatches->count() }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.reorder_suggestions') }}</div>
            <div class="mt-2 text-2xl font-black">{{ $reorderSuggestions->count() }}</div>
        </div>
        <div class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.pending_stock_counts') }}</div>
            <div class="mt-2 text-2xl font-black">{{ $recentCounts->where('status', 'draft')->count() }}</div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        @if(auth()->user()->hasPermission('inventory.count'))
            <section
                class="panel p-5"
                x-data="{
                    rows: [{ target: '', physical_quantity_base: '' }],
                    targets: @js($countTargets->values()),
                    add() { this.rows.push({ target: '', physical_quantity_base: '' }) },
                    remove(index) { if (this.rows.length > 1) this.rows.splice(index, 1) },
                    selected(value) { return this.targets.find(row => row.value === value) || null }
                }"
            >
                <h3 class="text-lg font-black">{{ __('ui.new_stock_count') }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ __('ui.stock_count_help') }}</p>

                <form method="POST" action="{{ route('inventory.stock-counts.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                    <template x-for="(row, index) in rows" :key="index">
                        <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem_auto]">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.count_target') }}</label>
                                    <select class="field" :name="'items['+index+'][target]'" x-model="row.target" required>
                                        <option value="">{{ __('ui.select_product_or_batch') }}</option>
                                        <template x-for="target in targets" :key="target.value">
                                            <option :value="target.value" x-text="target.label"></option>
                                        </template>
                                    </select>
                                    <template x-if="selected(row.target)">
                                        <div class="mt-1 text-xs text-slate-400">
                                            {{ __('ui.system_expected') }}:
                                            <span x-text="selected(row.target).stock"></span>
                                            <span x-text="selected(row.target).unit || ''"></span>
                                            <span x-show="selected(row.target).expires_at"> · {{ __('ui.expiry_date') }}: <span x-text="selected(row.target).expires_at"></span></span>
                                        </div>
                                    </template>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.physical_quantity') }}</label>
                                    <input class="field" :name="'items['+index+'][physical_quantity_base]'" x-model="row.physical_quantity_base" inputmode="decimal" required>
                                </div>
                                <div class="flex items-end">
                                    <button class="btn-secondary px-3" type="button" @click="remove(index)" :disabled="rows.length === 1">×</button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <button class="btn-secondary" type="button" @click="add()">{{ __('ui.add_count_line') }}</button>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.notes') }}</label>
                        <textarea class="field min-h-20" name="notes">{{ old('notes') }}</textarea>
                    </div>

                    <button class="btn-primary w-full" type="submit">{{ __('ui.save_count_draft') }}</button>
                </form>
            </section>
        @endif

        @if(auth()->user()->hasPermission('inventory.writeoff'))
            <section
                class="panel p-5"
                x-data="{
                    type: 'damage',
                    rows: [{ target: '', quantity_base: '' }],
                    damageTargets: @js($damageTargets->values()),
                    expiryTargets: @js($expiryTargets->values()),
                    add() { this.rows.push({ target: '', quantity_base: '' }) },
                    remove(index) { if (this.rows.length > 1) this.rows.splice(index, 1) },
                    options() { return this.type === 'expiry' ? this.expiryTargets : this.damageTargets },
                    selected(value) { return this.options().find(row => row.value === value) || null }
                }"
            >
                <h3 class="text-lg font-black">{{ __('ui.inventory_writeoff') }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ __('ui.inventory_writeoff_help') }}</p>

                <form method="POST" action="{{ route('inventory.writeoffs.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.writeoff_type') }}</label>
                        <select class="field" name="writeoff_type" x-model="type" @change="rows = [{ target: '', quantity_base: '' }]">
                            <option value="damage">{{ __('ui.damaged_stock') }}</option>
                            <option value="expiry">{{ __('ui.expired_stock') }}</option>
                        </select>
                    </div>

                    <template x-for="(row, index) in rows" :key="index">
                        <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem_auto]">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.stock_target') }}</label>
                                    <select class="field" :name="'items['+index+'][target]'" x-model="row.target" required>
                                        <option value="">{{ __('ui.select_product_or_batch') }}</option>
                                        <template x-for="target in options()" :key="target.value">
                                            <option :value="target.value" x-text="target.label"></option>
                                        </template>
                                    </select>
                                    <template x-if="selected(row.target)">
                                        <div class="mt-1 text-xs text-slate-400">
                                            {{ __('ui.available_stock') }}:
                                            <span x-text="selected(row.target).stock"></span>
                                            <span x-text="selected(row.target).unit || ''"></span>
                                            <span x-show="selected(row.target).expires_at"> · {{ __('ui.expiry_date') }}: <span x-text="selected(row.target).expires_at"></span></span>
                                        </div>
                                    </template>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.quantity') }}</label>
                                    <input class="field" :name="'items['+index+'][quantity_base]'" x-model="row.quantity_base" inputmode="decimal" required>
                                </div>
                                <div class="flex items-end">
                                    <button class="btn-secondary px-3" type="button" @click="remove(index)" :disabled="rows.length === 1">×</button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <button class="btn-secondary" type="button" @click="add()">{{ __('ui.add_writeoff_line') }}</button>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.reason') }}</label>
                        <input class="field" name="reason" required maxlength="500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.notes') }}</label>
                        <textarea class="field min-h-20" name="notes"></textarea>
                    </div>

                    <button class="btn-primary w-full" type="submit">{{ __('ui.post_writeoff') }}</button>
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
