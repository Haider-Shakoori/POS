@extends('layouts.app')

@section('title', __('ui.point_of_sale'))
@section('page-title', __('ui.point_of_sale'))

@section('content')
<div
    x-data="posWorkspace({
        searchUrl: @js(route('pos.products.search')),
        saleUrl: @js(route('pos.sales.store')),
        customerSearchUrl: @js(route('customers.search')),
        customerStoreUrl: @js(route('pos.customers.store')),
        heldBaseUrl: @js(url('/pos/held-sales')),
        csrf: @js(csrf_token()),
        hasOpenShift: @js($hasOpenShift),
        cashDrawerUrl: @js(route('cash.index')),
        canDiscount: @js($canDiscount),
        canCredit: @js($canCredit),
        canHold: @js($canHold),
        canQuickCreateCustomers: @js($canQuickCreateCustomers),
        paymentMethods: @js($paymentMethods),
        currency: '؋',
        labels: {
            searchFailed: @js(__('ui.pos_search_failed')),
            saleFailed: @js(__('ui.sale_failed')),
            saleCompleted: @js(__('ui.sale_completed')),
            customerSearchFailed: @js(__('ui.customer_search_failed')),
            customerCreateFailed: @js(__('ui.customer_create_failed')),
            customerRequired: @js(__('ui.customer_required_for_credit')),
            openShiftRequired: @js(__('ui.no_open_shift_message')),
            holdFailed: @js(__('ui.hold_sale_failed')),
            heldLoadFailed: @js(__('ui.held_sales_load_failed')),
            cartMustBeEmpty: @js(__('ui.cart_must_be_empty_to_resume')),
        }
    })"
    x-init="focusSearch()"
    @keydown.window="handleShortcut($event)"
    class="-m-4 min-h-[calc(100vh-4rem)] overflow-hidden bg-slate-100 sm:-m-6 lg:-m-8 dark:bg-slate-950"
>
    <div class="flex min-h-[calc(100vh-4rem)] flex-col">
        <div class="border-b border-slate-200 bg-white px-4 py-3 shadow-sm sm:px-5 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="grid size-10 shrink-0 place-items-center rounded-2xl bg-slate-950 text-lg font-black text-white shadow-sm dark:bg-white dark:text-slate-950">P</div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="truncate text-base font-black tracking-tight sm:text-lg">{{ __('ui.point_of_sale') }}</h2>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset"
                                :class="hasOpenShift ? 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900' : 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900'"
                            >
                                <span class="size-1.5 rounded-full" :class="hasOpenShift ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                <span x-text="hasOpenShift ? @js(__('ui.shift_open')) : @js(__('ui.shift_not_open'))"></span>
                            </span>
                        </div>
                        <div class="mt-0.5 text-xs text-slate-500">{{ __('ui.pos_server_totals_hint') }}</div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        x-show="canHold"
                        type="button"
                        class="btn-secondary hidden gap-2 md:inline-flex"
                        @click="openHeldSales()"
                    >
                        <span>{{ __('ui.held_sales') }}</span>
                        <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-black dark:bg-slate-800" x-text="heldSales.length"></span>
                        <kbd class="rounded-md border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[10px] dark:border-slate-700 dark:bg-slate-900">Shift+F9</kbd>
                    </button>

                    <a
                        x-show="!hasOpenShift"
                        :href="cashDrawerUrl"
                        class="inline-flex min-h-10 items-center rounded-xl bg-amber-500 px-3 text-xs font-black text-white shadow-sm transition hover:bg-amber-600"
                    >{{ __('ui.open_cash_drawer') }}</a>
                </div>
            </div>
        </div>

        <div x-show="message || lastSale" class="space-y-2 px-4 pt-3 sm:px-5">
            <div x-show="message" x-text="message" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 shadow-sm dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"></div>

            <div x-show="lastSale" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="grid size-7 place-items-center rounded-full bg-emerald-600 text-sm font-black text-white">✓</span>
                        <div>
                            <strong x-text="lastSale?.number"></strong> · {{ __('ui.sale_completed') }}
                            <span class="ms-2 font-black" x-text="lastSale ? money(lastSale.paid_amount) : ''"></span>
                        </div>
                    </div>
                    <a :href="lastSale?.url" class="font-black underline">{{ __('ui.view_sale') }}</a>
                </div>
            </div>
        </div>

        <div class="grid min-h-0 flex-1 gap-0 xl:grid-cols-[minmax(0,1fr)_31rem]">
            <section class="flex min-h-0 flex-col border-e border-slate-200 dark:border-slate-800">
                <div class="sticky top-0 z-20 border-b border-slate-200 bg-slate-100/95 px-4 py-4 backdrop-blur sm:px-5 dark:border-slate-800 dark:bg-slate-950/95">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                        <div class="relative min-w-0 flex-1">
                            <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <circle cx="11" cy="11" r="7"></circle>
                                    <path d="m20 20-3.5-3.5"></path>
                                </svg>
                            </div>
                            <input
                                x-ref="search"
                                x-model="query"
                                @input.debounce.200ms="searchProducts()"
                                @keydown.enter.prevent="acceptSearch()"
                                @keydown.escape.prevent="clearSearch()"
                                class="h-14 w-full rounded-2xl border border-slate-300 bg-white ps-12 pe-24 text-base font-semibold shadow-sm outline-none transition placeholder:font-medium placeholder:text-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-slate-700 dark:bg-slate-900 dark:focus:border-brand-500"
                                autocomplete="off"
                                placeholder="{{ __('ui.scan_search_placeholder') }}"
                            >
                            <div class="absolute inset-y-0 end-0 flex items-center gap-1 pe-3">
                                <span x-show="searching" class="size-4 animate-spin rounded-full border-2 border-slate-300 border-t-brand-600"></span>
                                <button x-show="query && !searching" type="button" class="rounded-lg px-2 py-1 text-xs font-black text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200" @click="clearSearch()">ESC</button>
                                <kbd class="hidden rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 font-mono text-[10px] font-black text-slate-500 sm:inline dark:border-slate-700 dark:bg-slate-800">F2</kbd>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-2 lg:justify-end">
                            <div class="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                <span>{{ __('ui.products') }}</span>
                                <span class="rounded-lg bg-slate-950 px-2 py-0.5 text-[11px] font-black text-white dark:bg-white dark:text-slate-950" x-text="results.length"></span>
                            </div>
                            <div class="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                <span>{{ __('ui.items') }}</span>
                                <span class="rounded-lg bg-brand-600 px-2 py-0.5 text-[11px] font-black text-white" x-text="cartQuantity()"></span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-2 hidden flex-wrap items-center gap-2 text-[11px] text-slate-400 md:flex">
                        <span>{{ __('ui.pos_enter_hint') }}</span>
                        <span class="text-slate-300 dark:text-slate-700">•</span>
                        <span><kbd class="font-mono font-black">F8</kbd> {{ __('ui.pay_and_complete') }}</span>
                        <span x-show="canHold"><span class="text-slate-300 dark:text-slate-700">•</span> <kbd class="font-mono font-black">F9</kbd> {{ __('ui.hold_sale') }}</span>
                        <span class="text-slate-300 dark:text-slate-700">•</span>
                        <span><kbd class="font-mono font-black">Ctrl+Enter</kbd> {{ __('ui.confirm_checkout') }}</span>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-5">
                    <div x-show="results.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5">
                        <template x-for="product in results" :key="product.product_unit_id">
                            <button
                                type="button"
                                @click="addProduct(product)"
                                class="group relative flex min-h-40 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white p-3.5 text-start shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-lg hover:shadow-slate-950/5 active:translate-y-0 active:scale-[0.99] dark:border-slate-800 dark:bg-slate-900 dark:hover:border-brand-700"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="grid size-11 shrink-0 place-items-center rounded-2xl bg-slate-100 text-sm font-black text-slate-600 transition group-hover:bg-brand-100 group-hover:text-brand-700 dark:bg-slate-800 dark:text-slate-300 dark:group-hover:bg-brand-950/60 dark:group-hover:text-brand-300" x-text="String(product.name || '?').trim().charAt(0).toUpperCase()"></div>
                                    <span
                                        x-show="product.track_stock"
                                        class="rounded-full px-2 py-1 text-[10px] font-black"
                                        :class="Number(product.available_quantity || 0) > 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300'"
                                    >
                                        <span x-text="formatQty(product.available_quantity)"></span>
                                    </span>
                                </div>

                                <div class="mt-3 min-w-0">
                                    <div class="line-clamp-2 min-h-10 text-sm font-black leading-5 text-slate-900 dark:text-white" x-text="product.name"></div>
                                    <div class="mt-1 truncate text-[11px] font-medium text-slate-400">
                                        <span x-text="product.sku"></span>
                                        <span class="mx-1">·</span>
                                        <span x-text="product.unit"></span>
                                    </div>
                                </div>

                                <div class="mt-auto flex items-end justify-between gap-2 pt-3">
                                    <div>
                                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('ui.sale_price') }}</div>
                                        <div class="mt-0.5 text-base font-black text-slate-950 dark:text-white" x-text="money(product.price)"></div>
                                    </div>
                                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-slate-950 text-xl font-light text-white shadow-sm transition group-hover:bg-brand-600 dark:bg-white dark:text-slate-950 dark:group-hover:bg-brand-500 dark:group-hover:text-white">+</span>
                                </div>
                            </button>
                        </template>
                    </div>

                    <div x-show="!searching && !results.length" class="grid min-h-[28rem] place-items-center text-center">
                        <div class="max-w-sm">
                            <div class="mx-auto grid size-20 place-items-center rounded-3xl bg-white text-4xl text-slate-300 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-600 dark:ring-slate-800">⌕</div>
                            <h3 class="mt-5 text-lg font-black">{{ __('ui.no_pos_products') }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('ui.pos_ready_message') }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="flex min-h-0 flex-col bg-white xl:max-h-[calc(100vh-4rem)] dark:bg-slate-900">
                <div class="border-b border-slate-200 px-4 py-4 sm:px-5 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-black">{{ __('ui.current_sale') }}</h3>
                                <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-black text-brand-700 dark:bg-brand-950/50 dark:text-brand-300"><span x-text="cart.length"></span> {{ __('ui.items') }}</span>
                            </div>
                            <div class="mt-1 truncate text-xs font-medium text-slate-500" x-text="selectedCustomer?.name || @js(__('ui.walk_in_customer'))"></div>
                        </div>

                        <button
                            x-show="canHold"
                            class="grid size-10 shrink-0 place-items-center rounded-xl border border-slate-200 text-slate-500 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700 dark:border-slate-700 dark:hover:border-brand-800 dark:hover:bg-brand-950/40 dark:hover:text-brand-300"
                            type="button"
                            @click="openHeldSales()"
                            title="{{ __('ui.held_sales') }}"
                        >
                            <span class="text-lg">⏸</span>
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
                    <div x-show="!cart.length" class="grid min-h-[24rem] place-items-center px-6 py-10 text-center">
                        <div class="max-w-xs">
                            <div class="mx-auto grid size-16 place-items-center rounded-3xl bg-slate-100 text-3xl text-slate-300 dark:bg-slate-800 dark:text-slate-600">▤</div>
                            <h4 class="mt-4 font-black">{{ __('ui.cart_empty') }}</h4>
                            <p class="mt-2 text-xs leading-5 text-slate-500">{{ __('ui.pos_enter_hint') }}</p>
                        </div>
                    </div>

                    <div x-show="cart.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template x-for="(item,index) in cart" :key="item.product_unit_id">
                            <div class="px-4 py-4 sm:px-5">
                                <div class="flex items-start gap-3">
                                    <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-xs font-black text-slate-500 dark:bg-slate-800 dark:text-slate-300" x-text="String(item.name || '?').trim().charAt(0).toUpperCase()"></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="truncate text-sm font-black" x-text="item.name"></div>
                                                <div class="mt-0.5 text-[11px] text-slate-400"><span x-text="item.unit"></span> · <span x-text="money(item.price)"></span></div>
                                            </div>
                                            <button type="button" class="grid size-8 shrink-0 place-items-center rounded-lg text-lg text-slate-300 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/30" @click="removeItem(index)">×</button>
                                        </div>

                                        <div class="mt-3 flex items-center justify-between gap-3">
                                            <div class="inline-grid grid-cols-[2.4rem_4.25rem_2.4rem] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                                                <button type="button" class="grid h-10 place-items-center text-lg font-bold text-slate-500 transition hover:bg-slate-50 hover:text-slate-950 dark:hover:bg-slate-800 dark:hover:text-white" @click="changeQty(index,-1)">−</button>
                                                <input class="h-10 w-full border-x border-slate-200 bg-transparent text-center text-sm font-black outline-none dark:border-slate-700" x-model="item.quantity" @change="normalizeQty(index)" :step="item.decimal_places > 0 ? Math.pow(10,-item.decimal_places) : 1" inputmode="decimal">
                                                <button type="button" class="grid h-10 place-items-center text-lg font-bold text-slate-500 transition hover:bg-slate-50 hover:text-slate-950 dark:hover:bg-slate-800 dark:hover:text-white" @click="changeQty(index,1)">+</button>
                                            </div>
                                            <div class="text-end">
                                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('ui.line_total') }}</div>
                                                <div class="mt-0.5 text-sm font-black" x-text="money(lineSubtotal(item))"></div>
                                            </div>
                                        </div>

                                        <div x-show="canDiscount" class="mt-3 flex items-center gap-2">
                                            <span class="shrink-0 text-[11px] font-bold text-slate-400">{{ __('ui.line_discount_afn') }}</span>
                                            <input class="h-9 min-w-0 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 text-end text-xs font-bold outline-none transition focus:border-brand-400 focus:bg-white dark:border-slate-700 dark:bg-slate-800 dark:focus:bg-slate-900" x-model="item.line_discount_amount" inputmode="decimal" min="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="border-t border-slate-200 bg-slate-50/80 p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-950/60">
                    <div x-show="canDiscount" class="mb-3">
                        <div class="flex items-center justify-between gap-3">
                            <label class="text-xs font-bold text-slate-500">{{ __('ui.sale_discount_afn') }}</label>
                            <input class="h-9 w-28 rounded-xl border border-slate-200 bg-white px-3 text-end text-xs font-black outline-none transition focus:border-brand-400 dark:border-slate-700 dark:bg-slate-900" x-model="saleDiscount" inputmode="decimal" min="0">
                        </div>
                    </div>

                    <div class="space-y-2 text-sm">
                        <div class="flex items-center justify-between text-slate-500">
                            <span>{{ __('ui.subtotal') }}</span>
                            <strong class="font-bold text-slate-700 dark:text-slate-200" x-text="money(subtotal())"></strong>
                        </div>
                        <div class="flex items-center justify-between text-slate-500">
                            <span>{{ __('ui.discount') }}</span>
                            <strong class="font-bold text-slate-700 dark:text-slate-200" x-text="money(totalDiscount())"></strong>
                        </div>
                    </div>

                    <div class="my-4 flex items-end justify-between border-y border-slate-200 py-4 dark:border-slate-800">
                        <div>
                            <div class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">{{ __('ui.total') }}</div>
                            <div class="mt-1 text-[11px] font-medium text-slate-400"><span x-text="cartQuantity()"></span> {{ __('ui.items') }}</div>
                        </div>
                        <div class="text-end text-3xl font-black tracking-tight text-slate-950 dark:text-white" x-text="money(total())"></div>
                    </div>

                    <div x-show="canHold" class="mb-2 grid grid-cols-2 gap-2">
                        <button class="btn-secondary min-h-11" type="button" @click="holdCurrentSale()" :disabled="!cart.length || holding">
                            <span x-show="!holding">{{ __('ui.hold_sale') }}</span>
                            <span x-show="holding">{{ __('ui.holding_sale') }}</span>
                            <kbd class="ms-1.5 rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] dark:bg-slate-800">F9</kbd>
                        </button>
                        <button class="btn-secondary min-h-11" type="button" @click="openHeldSales()">
                            {{ __('ui.held_sales') }}
                            <span class="ms-1 rounded-full bg-slate-200 px-1.5 py-0.5 text-[10px] font-black dark:bg-slate-700" x-text="heldSales.length"></span>
                        </button>
                    </div>

                    <button class="group flex min-h-14 w-full items-center justify-between rounded-2xl bg-slate-950 px-4 text-white shadow-lg shadow-slate-950/10 transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-white dark:text-slate-950 dark:hover:bg-brand-500 dark:hover:text-white" type="button" @click="openSettlement()" :disabled="!cart.length || submitting">
                        <div class="text-start">
                            <div class="text-sm font-black">{{ __('ui.pay_and_complete') }}</div>
                            <div class="mt-0.5 text-[10px] font-medium opacity-60">{{ __('ui.payment_server_notice') }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <kbd class="rounded-lg bg-white/10 px-2 py-1 font-mono text-[10px] font-black dark:bg-slate-950/10">F8</kbd>
                            <span class="text-xl transition group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5">→</span>
                        </div>
                    </button>
                </div>
            </aside>
        </div>
    </div>

    <div
        x-cloak
        x-show="paymentOpen"
        x-transition.opacity
        class="fixed inset-0 z-[80] overflow-y-auto bg-slate-950/80 p-0 backdrop-blur-md sm:p-4"
        @keydown.escape.window="if(!submitting) paymentOpen=false"
    >
        <div class="mx-auto min-h-full max-w-7xl sm:flex sm:min-h-0 sm:items-center sm:py-4">
            <div class="grid w-full overflow-hidden bg-slate-100 shadow-2xl sm:rounded-3xl lg:grid-cols-[minmax(0,1fr)_25rem] dark:bg-slate-950" @click.outside="if(!submitting) paymentOpen=false">
                <section class="min-w-0 bg-white dark:bg-slate-900">
                    <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6 dark:border-slate-800">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="grid size-11 shrink-0 place-items-center rounded-2xl bg-brand-600 text-xl font-black text-white shadow-lg shadow-brand-600/20">؋</div>
                            <div class="min-w-0">
                                <h3 class="truncate text-xl font-black tracking-tight">{{ __('ui.checkout_payment') }}</h3>
                                <p class="mt-0.5 text-xs text-slate-500">{{ __('ui.checkout_payment_help') }}</p>
                            </div>
                        </div>
                        <button class="grid size-10 shrink-0 place-items-center rounded-xl border border-slate-200 text-xl text-slate-400 transition hover:bg-slate-50 hover:text-slate-950 disabled:opacity-50 dark:border-slate-700 dark:hover:bg-slate-800 dark:hover:text-white" type="button" @click="paymentOpen=false" :disabled="submitting">×</button>
                    </div>

                    <div class="max-h-[calc(100vh-6rem)] overflow-y-auto p-4 sm:p-6 lg:max-h-[calc(100vh-8rem)]">
                        <div class="space-y-4">
                            <div x-show="checkoutMessage" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
                                <span x-text="checkoutMessage"></span>
                            </div>

                            <div x-show="requiresOpenShift() && !hasOpenShift" class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-amber-900 dark:bg-amber-950/40">
                                <div>
                                    <div class="text-sm font-black text-amber-800 dark:text-amber-300">{{ __('ui.no_open_shift_message') }}</div>
                                    <div class="mt-1 text-xs text-amber-700/70 dark:text-amber-300/70">{{ __('ui.cash_tendered') }}</div>
                                </div>
                                <a :href="cashDrawerUrl" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-amber-600 px-4 text-xs font-black text-white shadow-sm hover:bg-amber-700">{{ __('ui.view_cash_drawer') }}</a>
                            </div>

                            <section class="rounded-3xl border border-slate-200 bg-slate-50/60 p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-950/40">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">{{ __('ui.customer') }}</div>
                                        <div class="mt-1 text-sm font-bold text-slate-700 dark:text-slate-200" x-text="selectedCustomer?.name || @js(__('ui.walk_in_customer'))"></div>
                                    </div>
                                    <button x-show="selectedCustomer" class="rounded-lg px-2.5 py-1.5 text-xs font-black text-rose-600 transition hover:bg-rose-50 dark:hover:bg-rose-950/30" type="button" @click="clearCustomer()">{{ __('ui.remove_customer') }}</button>
                                </div>

                                <div x-show="selectedCustomer" class="mt-4 grid gap-3 rounded-2xl border border-brand-200 bg-white p-4 shadow-sm sm:grid-cols-2 dark:border-brand-900 dark:bg-slate-900">
                                    <div>
                                        <div class="text-base font-black" x-text="selectedCustomer?.name"></div>
                                        <div class="mt-1 text-xs text-slate-500" x-text="selectedCustomer?.phone || '—'"></div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-end text-xs">
                                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('ui.current_balance') }}</div>
                                            <strong class="mt-1 block text-sm" x-text="money(selectedCustomer?.current_balance)"></strong>
                                        </div>
                                        <div class="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/30">
                                            <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-600/70 dark:text-emerald-300/70">{{ __('ui.credit_available') }}</div>
                                            <strong class="mt-1 block text-sm text-emerald-700 dark:text-emerald-300" x-text="money(selectedCustomer?.available_credit)"></strong>
                                        </div>
                                    </div>
                                </div>

                                <div x-show="!selectedCustomer" class="mt-4">
                                    <div class="flex gap-2">
                                        <div class="relative min-w-0 flex-1">
                                            <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-slate-400">⌕</div>
                                            <input class="field ps-9" x-model="customerQuery" @input.debounce.250ms="searchCustomers()" placeholder="{{ __('ui.search_customer_pos') }}">
                                        </div>
                                        <button x-show="canQuickCreateCustomers" class="btn-secondary shrink-0 px-4" type="button" @click="customerCreateOpen=!customerCreateOpen">＋ {{ __('ui.add_customer') }}</button>
                                    </div>

                                    <div x-show="customerResults.length" class="mt-2 max-h-52 overflow-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                                        <template x-for="customer in customerResults" :key="customer.id">
                                            <button type="button" class="flex w-full items-center justify-between gap-3 rounded-xl px-3 py-3 text-start transition hover:bg-slate-50 dark:hover:bg-slate-800" @click="selectCustomer(customer)">
                                                <div class="min-w-0">
                                                    <div class="truncate font-bold" x-text="customer.name"></div>
                                                    <div class="mt-0.5 truncate text-xs text-slate-500" x-text="customer.phone || '—'"></div>
                                                </div>
                                                <div class="shrink-0 text-end text-[11px] text-slate-500">{{ __('ui.balance') }} <strong x-text="money(customer.current_balance)"></strong></div>
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                <div x-show="customerCreateOpen && canQuickCreateCustomers" class="mt-4 grid gap-3 rounded-2xl border border-dashed border-slate-300 bg-white p-4 dark:border-slate-700 dark:bg-slate-900 sm:grid-cols-3">
                                    <input class="field" x-model="newCustomer.name" placeholder="{{ __('ui.customer_name') }}">
                                    <input class="field" x-model="newCustomer.phone" placeholder="{{ __('ui.phone') }}">
                                    <input class="field" x-model="newCustomer.credit_limit" inputmode="decimal" placeholder="{{ __('ui.credit_limit') }}">
                                    <button class="btn-secondary sm:col-span-3" type="button" @click="createCustomer()" :disabled="customerCreating">
                                        <span x-show="!customerCreating">{{ __('ui.quick_create_customer') }}</span>
                                        <span x-show="customerCreating">{{ __('ui.creating') }}</span>
                                    </button>
                                </div>
                            </section>

                            <section class="rounded-3xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">{{ __('ui.payments') }}</div>
                                        <p class="mt-1 text-xs text-slate-500">{{ __('ui.split_payment_help') }}</p>
                                    </div>
                                    <button class="btn-secondary" type="button" @click="addPayment()">＋ {{ __('ui.add_payment') }}</button>
                                </div>

                                <div class="mt-4 space-y-3">
                                    <template x-for="(payment,index) in payments" :key="payment.key">
                                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-950/50">
                                            <div class="grid gap-3 md:grid-cols-[1.1fr_1fr_1fr_auto] md:items-end">
                                                <div>
                                                    <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-400">{{ __('ui.payment_method') }}</label>
                                                    <select class="field" x-model="payment.payment_method_id" @change="paymentMethodChanged(index)">
                                                        <template x-for="method in paymentMethods" :key="method.id">
                                                            <option :value="String(method.id)" x-text="method.name"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-400">{{ __('ui.applied_amount') }}</label>
                                                    <input class="field text-end font-black" x-model="payment.amount" @input="paymentAmountChanged(index)" inputmode="decimal">
                                                </div>
                                                <div x-show="isCashPayment(payment)">
                                                    <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-400">{{ __('ui.cash_tendered') }}</label>
                                                    <input class="field text-end font-black" x-model="payment.tendered_amount" inputmode="decimal">
                                                </div>
                                                <div x-show="!isCashPayment(payment)">
                                                    <label class="mb-1 block text-[10px] font-black uppercase tracking-wider text-slate-400">{{ __('ui.payment_reference') }}</label>
                                                    <input class="field" x-model="payment.reference">
                                                </div>
                                                <button type="button" class="grid size-11 place-items-center rounded-xl text-xl text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/30" @click="removePayment(index)">×</button>
                                            </div>
                                            <div x-show="isCashPayment(payment) && paymentChange(payment) > 0" class="mt-3 flex items-center justify-between rounded-xl bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300">
                                                <span>{{ __('ui.change') }}</span>
                                                <span x-text="money(paymentChange(payment))"></span>
                                            </div>
                                        </div>
                                    </template>

                                    <div x-show="!payments.length" class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-400 dark:border-slate-700">
                                        {{ __('ui.no_payment_credit_sale') }}
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </section>

                <aside class="flex flex-col border-t border-slate-200 bg-slate-950 text-white lg:border-s lg:border-t-0 dark:border-slate-800 dark:bg-black">
                    <div class="border-b border-white/10 p-5 sm:p-6">
                        <div class="text-[11px] font-black uppercase tracking-[0.18em] text-white/40">{{ __('ui.total') }}</div>
                        <div class="mt-2 text-4xl font-black tracking-tight" x-text="money(total())"></div>
                        <div class="mt-2 text-xs text-white/50"><span x-text="cartQuantity()"></span> {{ __('ui.items') }}</div>
                    </div>

                    <div class="flex-1 p-5 sm:p-6">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-white/55">{{ __('ui.payment_applied') }}</span>
                                <strong x-text="money(paymentAppliedTotal())"></strong>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-white/55">{{ __('ui.change') }}</span>
                                <strong class="text-emerald-300" x-text="money(totalChange())"></strong>
                            </div>
                            <div class="h-px bg-white/10"></div>
                            <div class="flex items-end justify-between gap-3">
                                <span class="text-sm font-black">{{ __('ui.credit_balance') }}</span>
                                <strong class="text-2xl font-black" :class="creditBalance() > 0 ? 'text-amber-300' : 'text-emerald-300'" x-text="money(creditBalance())"></strong>
                            </div>
                        </div>

                        <div x-show="creditBalance() > 0" class="mt-5 rounded-2xl border border-amber-400/20 bg-amber-400/10 p-4 text-xs leading-5 text-amber-200">
                            {{ __('ui.credit_customer_notice') }}
                        </div>

                        <button x-show="canCredit && selectedCustomer" class="mt-4 min-h-11 w-full rounded-xl border border-white/15 bg-white/5 px-4 text-sm font-black transition hover:bg-white/10" type="button" @click="payments=[]">
                            {{ __('ui.make_full_credit') }}
                        </button>
                    </div>

                    <div class="border-t border-white/10 p-5 sm:p-6">
                        <button class="group flex min-h-16 w-full items-center justify-between rounded-2xl bg-brand-500 px-4 text-white shadow-xl shadow-brand-950/20 transition hover:bg-brand-400 disabled:cursor-not-allowed disabled:opacity-40" type="button" @click="completeSale()" :disabled="submitting || (requiresOpenShift() && !hasOpenShift)">
                            <div class="text-start">
                                <div x-show="!submitting" class="text-base font-black">{{ __('ui.confirm_checkout') }}</div>
                                <div x-show="submitting" class="text-base font-black">{{ __('ui.posting_sale') }}</div>
                                <div class="mt-0.5 text-[10px] font-medium text-white/65">{{ __('ui.payment_server_notice') }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <kbd x-show="!submitting" class="rounded-lg bg-white/15 px-2 py-1 font-mono text-[10px] font-black">Ctrl+Enter</kbd>
                                <span x-show="!submitting" class="text-xl transition group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5">→</span>
                                <span x-show="submitting" class="size-5 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            </div>
                        </button>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    <div
        x-cloak
        x-show="heldOpen"
        x-transition.opacity
        class="fixed inset-0 z-[85] overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm"
        @keydown.escape.window="heldOpen=false"
    >
        <div class="panel mx-auto my-8 max-w-3xl overflow-hidden" @click.outside="heldOpen=false">
            <div class="flex items-center justify-between border-b border-slate-200 p-5 dark:border-slate-800">
                <div>
                    <h3 class="text-xl font-black">{{ __('ui.held_sales') }}</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ __('ui.held_sales_help') }}</p>
                </div>
                <button class="btn-secondary px-3" type="button" @click="heldOpen=false">×</button>
            </div>

            <div x-show="heldLoading" class="p-8 text-center text-sm text-slate-400">{{ __('ui.loading_held_sales') }}</div>
            <div x-show="!heldLoading && !heldSales.length" class="p-8 text-center text-sm text-slate-400">{{ __('ui.no_held_sales') }}</div>

            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <template x-for="held in heldSales" :key="held.id">
                    <div class="p-5">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                            <div>
                                <div class="font-black"><span x-text="held.number"></span> · <span x-text="held.customer_name"></span></div>
                                <div class="mt-1 text-xs text-slate-500">
                                    <span x-text="held.items.length"></span> {{ __('ui.items') }} · <span x-text="formatHeldTime(held.held_at)"></span>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-500">
                                    <template x-for="item in held.items.slice(0,4)" :key="item.product_unit_id">
                                        <span class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800"><span x-text="item.name"></span> × <span x-text="formatQty(item.quantity)"></span></span>
                                    </template>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button class="btn-primary" type="button" @click="resumeHeldSale(held)">{{ __('ui.resume') }}</button>
                                <button class="btn-secondary text-red-600" type="button" @click="releaseHeldSale(held)">{{ __('ui.release') }}</button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
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
        checkoutMessage: '',
        lastSale: null,
        saleKey: null,
        holdKey: null,
        hasOpenShift: Boolean(config.hasOpenShift),
        cashDrawerUrl: config.cashDrawerUrl,
        canDiscount: config.canDiscount,
        canCredit: config.canCredit,
        canHold: config.canHold,
        canQuickCreateCustomers: config.canQuickCreateCustomers,
        paymentMethods: config.paymentMethods || [],
        paymentOpen: false,
        payments: [],
        selectedCustomer: null,
        customerQuery: '',
        customerResults: [],
        customerCreateOpen: false,
        customerCreating: false,
        newCustomer: {name: '', phone: '', credit_limit: '0.00'},
        heldOpen: false,
        heldSales: [],
        heldLoading: false,
        holding: false,

        init() {
            this.resetSaleKey();
            this.resetHoldKey();
            this.searchProducts();

            if (this.canHold) {
                this.loadHeldSales();
            }
        },

        resetSaleKey() {
            this.saleKey = crypto.randomUUID();
        },

        resetHoldKey() {
            this.holdKey = crypto.randomUUID();
        },

        async searchProducts(autoAdd = false) {
            this.message = '';
            const term = this.query.trim();

            this.searching = true;

            try {
                const response = await fetch(config.searchUrl + '?q=' + encodeURIComponent(term), {
                    headers: {'Accept': 'application/json'}
                });

                if (!response.ok) throw new Error(config.labels.searchFailed);

                const payload = await response.json();
                this.results = payload.data || [];

                if (autoAdd && term && this.results.length === 1) {
                    this.addProduct(this.results[0]);
                }
            } catch (error) {
                this.results = [];
                this.message = error.message || config.labels.searchFailed;
            } finally {
                this.searching = false;
            }
        },

        acceptSearch() {
            if (!this.query.trim()) return;

            if (this.results.length === 1) {
                this.addProduct(this.results[0]);
                return;
            }

            this.searchProducts(true);
        },

        focusSearch() {
            this.$nextTick(() => {
                const search = this.$refs?.search;

                if (!this.paymentOpen && !this.heldOpen && search && typeof search.focus === 'function') {
                    search.focus({preventScroll: true});
                }
            });
        },

        isTypingTarget(event) {
            const target = event.target;

            return target instanceof HTMLElement
                && (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) || target.isContentEditable);
        },

        handleShortcut(event) {
            if (event.defaultPrevented || event.repeat) return;

            if (event.key === 'F2') {
                event.preventDefault();
                this.paymentOpen = false;
                this.heldOpen = false;
                this.focusSearch();
                return;
            }

            if (event.key === 'F8') {
                event.preventDefault();

                if (!this.paymentOpen && !this.heldOpen && this.cart.length) {
                    this.openSettlement();
                }

                return;
            }

            if (event.key === 'F9' && event.shiftKey) {
                event.preventDefault();

                if (this.canHold && !this.paymentOpen) {
                    this.openHeldSales();
                }

                return;
            }

            if (event.key === 'F9') {
                event.preventDefault();

                if (this.canHold && !this.paymentOpen && !this.heldOpen && this.cart.length && !this.holding) {
                    this.holdCurrentSale();
                }

                return;
            }

            if (event.ctrlKey && !event.metaKey && !event.altKey && event.key === 'Enter') {
                if (this.paymentOpen) {
                    event.preventDefault();
                    this.completeSale();
                }

                return;
            }

            if (this.isTypingTarget(event)) return;
        },

        clearSearch() {
            this.query = '';
            this.searchProducts();
            this.focusSearch();
        },

        addProduct(product) {
            const existing = this.cart.find(item => item.product_unit_id === product.product_unit_id);

            if (existing) {
                existing.quantity = String(Number(existing.quantity || 0) + 1);
            } else {
                this.cart.push({...product, quantity: '1', line_discount_amount: '0.00'});
            }

            this.clearSearch();
        },

        removeItem(index) {
            this.cart.splice(index, 1);
            this.focusSearch();
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

            if (!Number.isFinite(qty) || qty <= 0) {
                item.quantity = '1';
                return;
            }

            const places = Number(item.decimal_places || 0);
            item.quantity = String(Number(qty.toFixed(places)));
        },

        lineSubtotal(item) {
            return Number(item.quantity || 0) * Number(item.price || 0);
        },

        subtotal() {
            return this.cart.reduce((sum, item) => sum + this.lineSubtotal(item), 0);
        },

        cartQuantity() {
            return this.cart.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
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

        openSettlement() {
            if (!this.cart.length) return;

            this.message = '';
            this.checkoutMessage = '';
            this.heldOpen = false;
            this.paymentOpen = true;

            if (!this.payments.length) {
                this.resetDefaultPayment();
            }
        },

        openHeldSales() {
            if (!this.canHold) return;

            this.paymentOpen = false;
            this.heldOpen = true;
            this.loadHeldSales();
        },

        resetDefaultPayment() {
            const cash = this.paymentMethods.find(method => method.code === 'cash') || this.paymentMethods[0];

            if (!cash) {
                this.payments = [];
                return;
            }

            const amount = this.total().toFixed(2);
            this.payments = [{
                key: crypto.randomUUID(),
                payment_method_id: String(cash.id),
                amount,
                tendered_amount: cash.is_cash ? amount : '',
                reference: '',
            }];
        },

        addPayment() {
            const method = this.paymentMethods[0];
            if (!method) return;

            const remaining = Math.max(0, this.total() - this.paymentAppliedTotal());
            this.payments.push({
                key: crypto.randomUUID(),
                payment_method_id: String(method.id),
                amount: remaining.toFixed(2),
                tendered_amount: method.is_cash ? remaining.toFixed(2) : '',
                reference: '',
            });
        },

        removePayment(index) {
            this.payments.splice(index, 1);
        },

        methodFor(payment) {
            return this.paymentMethods.find(method => String(method.id) === String(payment.payment_method_id));
        },

        isCashPayment(payment) {
            return Boolean(this.methodFor(payment)?.is_cash);
        },

        paymentMethodChanged(index) {
            const payment = this.payments[index];

            if (this.isCashPayment(payment)) {
                payment.tendered_amount = payment.amount || '0.00';
                payment.reference = '';
            } else {
                payment.tendered_amount = '';
            }
        },

        paymentAmountChanged(index) {
            const payment = this.payments[index];

            if (this.isCashPayment(payment) && (!payment.tendered_amount || Number(payment.tendered_amount) < Number(payment.amount || 0))) {
                payment.tendered_amount = payment.amount || '0.00';
            }
        },

        paymentAppliedTotal() {
            return this.payments.reduce((sum, payment) => sum + Math.max(0, Number(payment.amount || 0)), 0);
        },

        creditBalance() {
            return Math.max(0, this.total() - this.paymentAppliedTotal());
        },

        paymentChange(payment) {
            if (!this.isCashPayment(payment)) return 0;
            return Math.max(0, Number(payment.tendered_amount || 0) - Number(payment.amount || 0));
        },

        totalChange() {
            return this.payments.reduce((sum, payment) => sum + this.paymentChange(payment), 0);
        },

        requiresOpenShift() {
            return this.payments.some(payment =>
                Number(payment.amount || 0) > 0 && this.isCashPayment(payment)
            );
        },

        async searchCustomers() {
            const term = this.customerQuery.trim();

            if (!term) {
                this.customerResults = [];
                return;
            }

            try {
                const response = await fetch(config.customerSearchUrl + '?q=' + encodeURIComponent(term), {
                    headers: {'Accept': 'application/json'}
                });

                if (!response.ok) throw new Error(config.labels.customerSearchFailed);

                const payload = await response.json();
                this.customerResults = payload.data || [];
            } catch (error) {
                this.customerResults = [];
                this.message = error.message || config.labels.customerSearchFailed;
            }
        },

        selectCustomer(customer) {
            this.selectedCustomer = customer;
            this.customerQuery = '';
            this.customerResults = [];
        },

        clearCustomer() {
            this.selectedCustomer = null;
            this.customerQuery = '';
            this.customerResults = [];
        },

        async createCustomer() {
            if (!this.canQuickCreateCustomers || !this.newCustomer.name.trim() || this.customerCreating) return;

            this.customerCreating = true;
            this.message = '';

            try {
                const response = await fetch(config.customerStoreUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body: JSON.stringify({
                        name: this.newCustomer.name.trim(),
                        phone: this.newCustomer.phone || null,
                        credit_limit: this.newCustomer.credit_limit || '0.00',
                        opening_balance: '0.00',
                    }),
                });

                const payload = await response.json();

                if (!response.ok) {
                    const validation = payload.errors ? Object.values(payload.errors).flat().join(' ') : payload.message;
                    throw new Error(validation || config.labels.customerCreateFailed);
                }

                this.selectedCustomer = {
                    ...payload.customer,
                    available_credit: String(Math.max(0, Number(payload.customer.credit_limit) - Number(payload.customer.current_balance)).toFixed(2)),
                };
                this.newCustomer = {name: '', phone: '', credit_limit: '0.00'};
                this.customerCreateOpen = false;
            } catch (error) {
                this.message = error.message || config.labels.customerCreateFailed;
            } finally {
                this.customerCreating = false;
            }
        },

        async loadHeldSales() {
            if (!this.canHold || this.heldLoading) return;

            this.heldLoading = true;

            try {
                const response = await fetch(config.heldBaseUrl, {
                    headers: {'Accept': 'application/json'}
                });

                if (!response.ok) throw new Error(config.labels.heldLoadFailed);

                const payload = await response.json();
                this.heldSales = payload.data || [];
            } catch (error) {
                this.message = error.message || config.labels.heldLoadFailed;
            } finally {
                this.heldLoading = false;
            }
        },

        async holdCurrentSale() {
            if (!this.canHold || !this.cart.length || this.holding) return;

            this.holding = true;
            this.message = '';

            try {
                const response = await fetch(config.heldBaseUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body: JSON.stringify({
                        idempotency_key: this.holdKey,
                        customer_id: this.selectedCustomer?.id || null,
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
                    const validation = payload.errors ? Object.values(payload.errors).flat().join(' ') : payload.message;
                    throw new Error(validation || config.labels.holdFailed);
                }

                this.resetTransaction();
                this.resetHoldKey();
                await this.loadHeldSales();
                this.message = payload.message || '';
            } catch (error) {
                this.message = error.message || config.labels.holdFailed;
            } finally {
                this.holding = false;
            }
        },

        async resumeHeldSale(held) {
            if (this.cart.length) {
                this.message = config.labels.cartMustBeEmpty;
                this.heldOpen = false;
                return;
            }

            try {
                const response = await fetch(config.heldBaseUrl + '/' + held.id + '/resume', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                });
                const payload = await response.json();

                if (!response.ok) throw new Error(payload.message || config.labels.holdFailed);

                const resumed = payload.held_sale;
                this.cart = (resumed.items || []).map(item => ({
                    ...item,
                    quantity: String(item.quantity),
                    line_discount_amount: String(item.line_discount_amount || '0'),
                }));
                this.saleDiscount = String(resumed.sale_discount_amount || '0');
                this.selectedCustomer = resumed.customer ? {
                    ...resumed.customer,
                    available_credit: String(Math.max(
                        0,
                        Number(resumed.customer.credit_limit || 0) - Number(resumed.customer.current_balance || 0)
                    ).toFixed(2)),
                } : null;
                this.payments = [];
                this.resetSaleKey();
                this.resetHoldKey();
                this.heldOpen = false;
                await this.loadHeldSales();
                this.focusSearch();
            } catch (error) {
                this.message = error.message || config.labels.holdFailed;
            }
        },

        async releaseHeldSale(held) {
            try {
                const response = await fetch(config.heldBaseUrl + '/' + held.id + '/release', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                });
                const payload = await response.json();

                if (!response.ok) throw new Error(payload.message || config.labels.holdFailed);

                await this.loadHeldSales();
            } catch (error) {
                this.message = error.message || config.labels.holdFailed;
            }
        },

        formatHeldTime(value) {
            if (!value) return '';
            return new Date(value).toLocaleString();
        },

        resetTransaction() {
            this.cart = [];
            this.saleDiscount = '0.00';
            this.paymentOpen = false;
            this.payments = [];
            this.checkoutMessage = '';
            this.selectedCustomer = null;
            this.customerQuery = '';
            this.customerResults = [];
            this.resetSaleKey();
            this.focusSearch();
        },

        async completeSale() {
            if (!this.cart.length || this.submitting) return;

            this.checkoutMessage = '';

            if (this.requiresOpenShift() && !this.hasOpenShift) {
                this.checkoutMessage = config.labels.openShiftRequired;
                return;
            }

            if (this.creditBalance() > 0 && !this.selectedCustomer) {
                this.checkoutMessage = config.labels.customerRequired;
                return;
            }

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
                        customer_id: this.selectedCustomer?.id || null,
                        sale_discount_amount: this.canDiscount ? String(this.saleDiscount || '0') : '0',
                        items: this.cart.map(item => ({
                            product_unit_id: item.product_unit_id,
                            quantity: String(item.quantity),
                            line_discount_amount: this.canDiscount ? String(item.line_discount_amount || '0') : '0',
                        })),
                        payments: this.payments
                            .filter(payment => Number(payment.amount || 0) > 0)
                            .map(payment => ({
                                payment_method_id: Number(payment.payment_method_id),
                                amount: String(payment.amount),
                                tendered_amount: this.isCashPayment(payment) ? String(payment.tendered_amount || payment.amount) : null,
                                reference: payment.reference || null,
                            })),
                    }),
                });

                const payload = await response.json();

                if (!response.ok) {
                    const validation = payload.errors ? Object.values(payload.errors).flat().join(' ') : payload.message;
                    throw new Error(validation || config.labels.saleFailed);
                }

                this.lastSale = payload.sale;
                this.message = '';
                this.resetTransaction();
                this.resetHoldKey();
            } catch (error) {
                this.checkoutMessage = error.message || config.labels.saleFailed;
            } finally {
                this.submitting = false;
            }
        },
    };
}
</script>
@endsection
