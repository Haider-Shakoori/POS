@extends('layouts.app')

@section('title', __('ui.point_of_sale'))
@section('page-title', __('ui.point_of_sale'))

@section('content')
<div
    x-data="posWorkspace({
        searchUrl: @js(route('pos.products.search')),
        saleUrl: @js(route('pos.sales.store')),
        csrf: @js(csrf_token()),
        canDiscount: @js($canDiscount),
        currency: '؋',
        labels: {
            searchFailed: @js(__('ui.pos_search_failed')),
            saleFailed: @js(__('ui.sale_failed')),
            saleCompleted: @js(__('ui.sale_completed')),
        }
    })"
    x-init="$nextTick(() => $refs.search.focus())"
    class="grid min-h-[calc(100vh-9rem)] gap-4 xl:grid-cols-[minmax(0,1fr)_28rem]"
>
    <section class="panel flex min-h-[36rem] flex-col overflow-hidden">
        <div class="border-b border-slate-200 p-4 dark:border-slate-800">
            <div class="relative">
                <input
                    x-ref="search"
                    x-model="query"
                    @input.debounce.250ms="searchProducts()"
                    @keydown.enter.prevent="acceptSearch()"
                    @keydown.escape.prevent="clearSearch()"
                    class="field py-3 ps-11 pe-12"
                    autocomplete="off"
                    placeholder="{{ __('ui.scan_search_placeholder') }}"
                >
                <span class="absolute start-4 top-1/2 -translate-y-1/2 text-slate-400">⌕</span>
                <span x-show="searching" class="absolute end-4 top-1/2 -translate-y-1/2 animate-pulse text-xs text-slate-400">{{ __('ui.searching') }}</span>
            </div>
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-400">
                <span>{{ __('ui.pos_enter_hint') }}</span>
                <span>{{ __('ui.pos_server_totals_hint') }}</span>
            </div>
        </div>

        <div x-show="message" x-text="message" class="m-4 mb-0 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"></div>

        <div x-show="lastSale" class="m-4 mb-0 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><strong x-text="lastSale?.number"></strong> · {{ __('ui.sale_completed') }}</div>
                <a :href="lastSale?.url" class="font-bold underline">{{ __('ui.view_sale') }}</a>
            </div>
        </div>

        <div class="flex-1 overflow-auto p-4">
            <div x-show="query && results.length" class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                <template x-for="product in results" :key="product.product_unit_id">
                    <button
                        type="button"
                        @click="addProduct(product)"
                        class="rounded-2xl border border-slate-200 p-4 text-start transition hover:border-brand-400 hover:bg-brand-50/60 dark:border-slate-800 dark:hover:border-brand-700 dark:hover:bg-brand-950/30"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-bold" x-text="product.name"></div>
                                <div class="mt-1 text-xs text-slate-500"><span x-text="product.sku"></span> · <span x-text="product.unit"></span></div>
                            </div>
                            <div class="shrink-0 text-end font-black"><span x-text="money(product.price)"></span></div>
                        </div>
                        <div class="mt-4 flex items-center justify-between text-xs text-slate-500">
                            <span x-show="product.track_stock">{{ __('ui.available') }}: <strong x-text="formatQty(product.available_quantity)"></strong></span>
                            <span x-show="!product.track_stock">{{ __('ui.untracked_stock') }}</span>
                            <span x-show="product.track_expiry" class="rounded-full bg-amber-50 px-2 py-1 font-bold text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">{{ __('ui.fefo') }}</span>
                        </div>
                    </button>
                </template>
            </div>

            <div x-show="query && !searching && !results.length" class="grid min-h-72 place-items-center text-center text-sm text-slate-400">
                <div><div class="text-4xl">⌕</div><div class="mt-3">{{ __('ui.no_pos_products') }}</div></div>
            </div>

            <div x-show="!query" class="grid min-h-72 place-items-center p-8 text-center">
                <div class="max-w-md">
                    <div class="mx-auto grid size-16 place-items-center rounded-3xl bg-brand-50 text-3xl text-brand-700 dark:bg-brand-950/60 dark:text-brand-300">▦</div>
                    <h2 class="mt-5 text-2xl font-black">{{ __('ui.pos_ready') }}</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-500">{{ __('ui.pos_ready_message') }}</p>
                </div>
            </div>
        </div>
    </section>

    <aside class="panel flex min-h-[36rem] flex-col overflow-hidden">
        <div class="border-b border-slate-200 p-5 dark:border-slate-800">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="font-black">{{ __('ui.current_sale') }}</h3>
                    <div class="mt-1 text-xs text-slate-500">{{ __('ui.walk_in_customer') }}</div>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500 dark:bg-slate-800"><span x-text="cart.length"></span> {{ __('ui.items') }}</span>
            </div>
        </div>

        <div class="flex-1 overflow-auto">
            <div x-show="!cart.length" class="grid min-h-64 place-items-center p-6 text-center text-sm text-slate-400">{{ __('ui.cart_empty') }}</div>

            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <template x-for="(item,index) in cart" :key="item.product_unit_id">
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-bold" x-text="item.name"></div>
                                <div class="mt-1 text-xs text-slate-500"><span x-text="item.unit"></span> · <span x-text="money(item.price)"></span></div>
                            </div>
                            <button type="button" class="text-lg text-slate-400 hover:text-red-600" @click="removeItem(index)">×</button>
                        </div>

                        <div class="mt-3 grid grid-cols-[2.1rem_minmax(0,1fr)_2.1rem_7rem] gap-2">
                            <button type="button" class="btn-secondary px-0" @click="changeQty(index,-1)">−</button>
                            <input class="field text-center" x-model="item.quantity" @change="normalizeQty(index)" inputmode="decimal">
                            <button type="button" class="btn-secondary px-0" @click="changeQty(index,1)">+</button>
                            <div class="grid place-items-center rounded-xl bg-slate-50 px-2 text-end text-sm font-bold dark:bg-slate-800/60" x-text="money(lineSubtotal(item))"></div>
                        </div>

                        <div x-show="canDiscount" class="mt-3">
                            <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.line_discount_afn') }}</label>
                            <input class="field" x-model="item.line_discount_amount" inputmode="decimal" min="0">
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="space-y-3 border-t border-slate-200 p-5 dark:border-slate-800">
            <div x-show="canDiscount">
                <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.sale_discount_afn') }}</label>
                <input class="field" x-model="saleDiscount" inputmode="decimal" min="0">
            </div>

            <div class="flex justify-between text-sm"><span>{{ __('ui.subtotal') }}</span><strong x-text="money(subtotal())"></strong></div>
            <div class="flex justify-between text-sm"><span>{{ __('ui.discount') }}</span><strong x-text="money(totalDiscount())"></strong></div>
            <div class="flex justify-between border-t border-slate-200 pt-3 text-xl dark:border-slate-800"><span class="font-black">{{ __('ui.total') }}</span><strong x-text="money(total())"></strong></div>

            <button class="btn-primary w-full py-3.5" type="button" @click="completeSale()" :disabled="!cart.length || submitting">
                <span x-show="!submitting">{{ __('ui.complete_sale') }}</span>
                <span x-show="submitting">{{ __('ui.posting_sale') }}</span>
            </button>
            <p class="text-xs leading-5 text-slate-500">{{ __('ui.payment_next_batch_notice') }}</p>
        </div>
    </aside>
</div>

<script>
function posWorkspace(config) {
    return {
        query: '',
        results: [],
        cart: [],
        saleDiscount: '0.00',
        searching: false,
        submitting: false,
        message: '',
        lastSale: null,
        saleKey: null,
        canDiscount: config.canDiscount,

        init() {
            this.resetSaleKey();
        },

        resetSaleKey() {
            this.saleKey = crypto.randomUUID();
        },

        async searchProducts() {
            this.message = '';
            const term = this.query.trim();

            if (!term) {
                this.results = [];
                return;
            }

            this.searching = true;

            try {
                const response = await fetch(config.searchUrl + '?q=' + encodeURIComponent(term), {
                    headers: {'Accept': 'application/json'}
                });

                if (!response.ok) throw new Error(config.labels.searchFailed);

                const payload = await response.json();
                this.results = payload.data || [];
            } catch (error) {
                this.results = [];
                this.message = error.message || config.labels.searchFailed;
            } finally {
                this.searching = false;
            }
        },

        acceptSearch() {
            if (this.results.length === 1) {
                this.addProduct(this.results[0]);
                return;
            }

            this.searchProducts();
        },

        clearSearch() {
            this.query = '';
            this.results = [];
            this.$nextTick(() => this.$refs.search.focus());
        },

        addProduct(product) {
            const existing = this.cart.find(item => item.product_unit_id === product.product_unit_id);

            if (existing) {
                existing.quantity = String(Number(existing.quantity || 0) + 1);
            } else {
                this.cart.push({
                    ...product,
                    quantity: '1',
                    line_discount_amount: '0.00',
                });
            }

            this.clearSearch();
        },

        removeItem(index) {
            this.cart.splice(index, 1);
            this.$nextTick(() => this.$refs.search.focus());
        },

        changeQty(index, delta) {
            const item = this.cart[index];
            const next = Number(item.quantity || 0) + delta;

            if (next <= 0) {
                this.removeItem(index);
                return;
            }

            item.quantity = String(next);
        },

        normalizeQty(index) {
            const item = this.cart[index];
            const qty = Number(item.quantity);

            if (!Number.isFinite(qty) || qty <= 0) item.quantity = '1';
        },

        lineSubtotal(item) {
            return Number(item.quantity || 0) * Number(item.price || 0);
        },

        subtotal() {
            return this.cart.reduce((sum, item) => sum + this.lineSubtotal(item), 0);
        },

        lineDiscountTotal() {
            return this.cart.reduce((sum, item) => sum + Number(item.line_discount_amount || 0), 0);
        },

        totalDiscount() {
            return this.lineDiscountTotal() + Number(this.saleDiscount || 0);
        },

        total() {
            return Math.max(0, this.subtotal() - this.totalDiscount());
        },

        money(value) {
            const amount = Number(value || 0);
            return config.currency + ' ' + amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        },

        formatQty(value) {
            if (value === null || value === undefined) return '—';
            return Number(value).toLocaleString(undefined, {maximumFractionDigits: 6});
        },

        async completeSale() {
            if (!this.cart.length || this.submitting) return;

            this.submitting = true;
            this.message = '';
            this.lastSale = null;

            try {
                const response = await fetch(config.saleUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body: JSON.stringify({
                        idempotency_key: this.saleKey,
                        sale_discount_amount: this.canDiscount ? String(this.saleDiscount || '0') : '0',
                        items: this.cart.map(item => ({
                            product_unit_id: item.product_unit_id,
                            quantity: String(item.quantity),
                            line_discount_amount: this.canDiscount ? String(item.line_discount_amount || '0') : '0',
                        })),
                    }),
                });

                const payload = await response.json();

                if (!response.ok) {
                    const validation = payload.errors
                        ? Object.values(payload.errors).flat().join(' ')
                        : payload.message;
                    throw new Error(validation || config.labels.saleFailed);
                }

                this.lastSale = payload.sale;
                this.cart = [];
                this.saleDiscount = '0.00';
                this.message = '';
                this.resetSaleKey();
                this.$nextTick(() => this.$refs.search.focus());
            } catch (error) {
                this.message = error.message || config.labels.saleFailed;
            } finally {
                this.submitting = false;
            }
        },
    };
}
</script>
@endsection
