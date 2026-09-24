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
    class="grid min-h-[calc(100vh-9rem)] gap-4 xl:grid-cols-[minmax(0,1fr)_30rem]"
>
    <section class="panel flex min-h-[36rem] flex-col overflow-hidden">
        <div class="border-b border-slate-200 bg-gradient-to-b from-slate-50/90 to-white p-4 dark:border-slate-800 dark:from-slate-900/70 dark:to-slate-900">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                <div class="min-w-0 flex-1">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs font-black uppercase tracking-[0.16em] text-brand-600 dark:text-brand-300">{{ __('ui.point_of_sale') }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ __('ui.pos_enter_hint') }}</div>
                        </div>
                        <button type="button" class="hidden items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-500 shadow-sm hover:border-brand-300 hover:text-brand-700 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-brand-700 dark:hover:text-brand-300 sm:flex" @click="focusSearch()">
                            <kbd class="font-mono text-[11px]">F2</kbd>
                            <span>{{ __('ui.search') }}</span>
                        </button>
                    </div>
                    <div class="relative">
                        <input
                            x-ref="search"
                            x-model="query"
                            @input.debounce.250ms="searchProducts()"
                            @keydown.enter.prevent="acceptSearch()"
                            @keydown.escape.prevent="clearSearch()"
                            class="field py-3.5 ps-11 pe-24 text-base shadow-sm"
                            autocomplete="off"
                            placeholder="{{ __('ui.scan_search_placeholder') }}"
                        >
                        <span class="absolute start-4 top-1/2 -translate-y-1/2 text-lg text-slate-400">⌕</span>
                        <button x-show="query" type="button" class="absolute end-3 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-bold text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200" @click="clearSearch()">Esc</button>
                        <span x-show="searching" class="absolute end-12 top-1/2 -translate-y-1/2 animate-pulse text-xs text-slate-400">{{ __('ui.searching') }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:flex lg:shrink-0">
                    <button x-show="canHold" class="btn-secondary min-h-12" type="button" @click="holdCurrentSale()" :disabled="!cart.length || holding">
                        <span class="me-2">{{ __('ui.hold_sale') }}</span>
                        <kbd class="rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] dark:bg-slate-800">F9</kbd>
                    </button>
                    <button class="btn-primary min-h-12" type="button" @click="openSettlement()" :disabled="!cart.length || submitting">
                        <span class="me-2">{{ __('ui.pay_and_complete') }}</span>
                        <kbd class="rounded-md bg-white/20 px-1.5 py-0.5 font-mono text-[10px]">F8</kbd>
                    </button>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                <span class="font-semibold">{{ __('ui.pos_server_totals_hint') }}</span>
                <span class="hidden h-4 w-px bg-slate-200 dark:bg-slate-700 sm:block"></span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-1 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700"><kbd class="font-mono font-bold">F2</kbd><span>{{ __('ui.search') }}</span></span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-1 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700"><kbd class="font-mono font-bold">F8</kbd><span>{{ __('ui.pay_and_complete') }}</span></span>
                <span x-show="canHold" class="inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-1 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700"><kbd class="font-mono font-bold">F9</kbd><span>{{ __('ui.hold_sale') }}</span></span>
                <span x-show="canHold" class="inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-1 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700"><kbd class="font-mono font-bold">Shift+F9</kbd><span>{{ __('ui.held_sales') }}</span></span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-1 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700"><kbd class="font-mono font-bold">Ctrl+Enter</kbd><span>{{ __('ui.confirm_checkout') }}</span></span>
            </div>
        </div>

        <div x-show="message" x-text="message" class="m-4 mb-0 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"></div>

        <div x-show="lastSale" class="m-4 mb-0 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <strong x-text="lastSale?.number"></strong> · {{ __('ui.sale_completed') }}
                    <span class="ms-2" x-text="lastSale ? money(lastSale.paid_amount) : ''"></span>
                </div>
                <a :href="lastSale?.url" class="font-bold underline">{{ __('ui.view_sale') }}</a>
            </div>
        </div>

        <div class="flex-1 overflow-auto p-4">
            <div x-show="query && results.length" class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                <template x-for="product in results" :key="product.product_unit_id">
                    <button
                        type="button"
                        @click="addProduct(product)"
                        class="group rounded-2xl border border-slate-200 bg-white p-4 text-start shadow-sm transition hover:-translate-y-0.5 hover:border-brand-400 hover:bg-brand-50/60 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-brand-700 dark:hover:bg-brand-950/30"
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

    <aside class="panel flex min-h-[36rem] flex-col overflow-hidden xl:sticky xl:top-4 xl:max-h-[calc(100vh-10rem)]">
        <div class="border-b border-slate-200 bg-slate-50/70 p-5 dark:border-slate-800 dark:bg-slate-900/60">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="font-black">{{ __('ui.current_sale') }}</h3>
                    <div class="mt-1 text-xs text-slate-500" x-text="selectedCustomer?.name || @js(__('ui.walk_in_customer'))"></div>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500 dark:bg-slate-800"><span x-text="cart.length"></span> {{ __('ui.items') }}</span>
            </div>
        </div>

        <div class="flex-1 overflow-auto">
            <div x-show="!cart.length" class="grid min-h-64 place-items-center p-6 text-center text-sm text-slate-400">{{ __('ui.cart_empty') }}</div>

            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <template x-for="(item,index) in cart" :key="item.product_unit_id">
                    <div class="m-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-bold" x-text="item.name"></div>
                                <div class="mt-1 text-xs text-slate-500"><span x-text="item.unit"></span> · <span x-text="money(item.price)"></span></div>
                            </div>
                            <button type="button" class="text-lg text-slate-400 hover:text-red-600" @click="removeItem(index)">×</button>
                        </div>

                        <div class="mt-3 grid grid-cols-[2.1rem_minmax(0,1fr)_2.1rem_7rem] gap-2">
                            <button type="button" class="btn-secondary px-0" @click="changeQty(index,-1)">−</button>
                            <input class="field text-center" x-model="item.quantity" @change="normalizeQty(index)" :step="item.decimal_places > 0 ? Math.pow(10,-item.decimal_places) : 1" inputmode="decimal">
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

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                <div class="flex justify-between text-sm"><span>{{ __('ui.subtotal') }}</span><strong x-text="money(subtotal())"></strong></div>
                <div class="mt-2 flex justify-between text-sm text-slate-500"><span>{{ __('ui.discount') }}</span><strong x-text="money(totalDiscount())"></strong></div>
                <div class="mt-3 flex justify-between border-t border-slate-200 pt-3 text-2xl dark:border-slate-800">
                    <span class="font-black">{{ __('ui.total') }}</span>
                    <strong class="text-brand-700 dark:text-brand-300" x-text="money(total())"></strong>
                </div>
            </div>

            <div x-show="canHold" class="grid grid-cols-2 gap-2">
                <button class="btn-secondary" type="button" @click="holdCurrentSale()" :disabled="!cart.length || holding">
                    <span x-show="!holding">{{ __('ui.hold_sale') }}</span>
                    <span x-show="holding">{{ __('ui.holding_sale') }}</span>
                    <kbd class="ms-2 rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] dark:bg-slate-800">F9</kbd>
                </button>
                <button class="btn-secondary" type="button" @click="openHeldSales()">
                    {{ __('ui.held_sales') }}
                    <span class="ms-1 rounded-full bg-slate-200 px-1.5 py-0.5 text-[10px] dark:bg-slate-700" x-text="heldSales.length"></span>
                    <kbd class="ms-2 hidden rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] sm:inline dark:bg-slate-800">Shift+F9</kbd>
                </button>
            </div>

            <button class="btn-primary w-full py-4 text-base shadow-sm" type="button" @click="openSettlement()" :disabled="!cart.length || submitting">
                <span>{{ __('ui.pay_and_complete') }}</span>
                <kbd class="ms-2 rounded-md bg-white/20 px-1.5 py-0.5 font-mono text-[10px]">F8</kbd>
            </button>
            <p class="text-xs leading-5 text-slate-500">{{ __('ui.payment_server_notice') }}</p>
        </div>
    </aside>

    <div
        x-cloak
        x-show="paymentOpen"
        x-transition.opacity
        class="fixed inset-0 z-[80] overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm"
        @keydown.escape.window="if(!submitting) paymentOpen=false"
    >
        <div class="mx-auto my-4 grid max-w-6xl gap-4 xl:grid-cols-[1fr_24rem]" @click.outside="if(!submitting) paymentOpen=false">
            <section class="panel overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-200 p-5 dark:border-slate-800">
                    <div>
                        <h3 class="text-xl font-black">{{ __('ui.checkout_payment') }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ __('ui.checkout_payment_help') }}</p>
                    </div>
                    <button class="btn-secondary px-3" type="button" @click="paymentOpen=false" :disabled="submitting">×</button>
                </div>

                <div class="space-y-5 p-5">
                    <div x-show="checkoutMessage" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                        <span x-text="checkoutMessage"></span>
                    </div>

                    <div x-show="requiresOpenShift() && !hasOpenShift" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
                        <div class="font-bold">{{ __('ui.no_open_shift_message') }}</div>
                        <a :href="cashDrawerUrl" class="mt-2 inline-block font-bold underline">{{ __('ui.view_cash_drawer') }}</a>
                    </div>

                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <label class="text-sm font-bold">{{ __('ui.customer') }}</label>
                            <button x-show="selectedCustomer" class="text-xs font-bold text-red-600" type="button" @click="clearCustomer()">{{ __('ui.remove_customer') }}</button>
                        </div>

                        <div x-show="selectedCustomer" class="mt-2 rounded-xl border border-brand-200 bg-brand-50 p-3 dark:border-brand-900 dark:bg-brand-950/30">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="font-bold" x-text="selectedCustomer?.name"></div>
                                    <div class="mt-1 text-xs text-slate-500" x-text="selectedCustomer?.phone || '—'"></div>
                                </div>
                                <div class="text-end text-xs">
                                    <div>{{ __('ui.current_balance') }}: <strong x-text="money(selectedCustomer?.current_balance)"></strong></div>
                                    <div class="mt-1">{{ __('ui.credit_available') }}: <strong x-text="money(selectedCustomer?.available_credit)"></strong></div>
                                </div>
                            </div>
                        </div>

                        <div x-show="!selectedCustomer" class="mt-2">
                            <div class="flex gap-2">
                                <input class="field" x-model="customerQuery" @input.debounce.250ms="searchCustomers()" placeholder="{{ __('ui.search_customer_pos') }}">
                                <button x-show="canQuickCreateCustomers" class="btn-secondary shrink-0" type="button" @click="customerCreateOpen=!customerCreateOpen">＋</button>
                            </div>

                            <div x-show="customerResults.length" class="mt-2 max-h-48 overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
                                <template x-for="customer in customerResults" :key="customer.id">
                                    <button type="button" class="flex w-full items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-start last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800" @click="selectCustomer(customer)">
                                        <div>
                                            <div class="font-semibold" x-text="customer.name"></div>
                                            <div class="mt-1 text-xs text-slate-500" x-text="customer.phone || '—'"></div>
                                        </div>
                                        <div class="text-end text-xs text-slate-500">{{ __('ui.balance') }} <strong x-text="money(customer.current_balance)"></strong></div>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div x-show="customerCreateOpen && canQuickCreateCustomers" class="mt-3 grid gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700 sm:grid-cols-3">
                            <input class="field" x-model="newCustomer.name" placeholder="{{ __('ui.customer_name') }}">
                            <input class="field" x-model="newCustomer.phone" placeholder="{{ __('ui.phone') }}">
                            <input class="field" x-model="newCustomer.credit_limit" inputmode="decimal" placeholder="{{ __('ui.credit_limit') }}">
                            <button class="btn-secondary sm:col-span-3" type="button" @click="createCustomer()" :disabled="customerCreating">
                                <span x-show="!customerCreating">{{ __('ui.quick_create_customer') }}</span>
                                <span x-show="customerCreating">{{ __('ui.creating') }}</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <div>
                                <h4 class="font-black">{{ __('ui.payments') }}</h4>
                                <p class="mt-1 text-xs text-slate-500">{{ __('ui.split_payment_help') }}</p>
                            </div>
                            <button class="btn-secondary" type="button" @click="addPayment()">{{ __('ui.add_payment') }}</button>
                        </div>

                        <div class="space-y-3">
                            <template x-for="(payment,index) in payments" :key="payment.key">
                                <div class="grid gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700 md:grid-cols-[1.1fr_1fr_1fr_auto]">
                                    <select class="field" x-model="payment.payment_method_id" @change="paymentMethodChanged(index)">
                                        <template x-for="method in paymentMethods" :key="method.id">
                                            <option :value="String(method.id)" x-text="method.name"></option>
                                        </template>
                                    </select>
                                    <div>
                                        <label class="mb-1 block text-[11px] font-semibold text-slate-500">{{ __('ui.applied_amount') }}</label>
                                        <input class="field" x-model="payment.amount" @input="paymentAmountChanged(index)" inputmode="decimal">
                                    </div>
                                    <div x-show="isCashPayment(payment)">
                                        <label class="mb-1 block text-[11px] font-semibold text-slate-500">{{ __('ui.cash_tendered') }}</label>
                                        <input class="field" x-model="payment.tendered_amount" inputmode="decimal">
                                    </div>
                                    <div x-show="!isCashPayment(payment)">
                                        <label class="mb-1 block text-[11px] font-semibold text-slate-500">{{ __('ui.payment_reference') }}</label>
                                        <input class="field" x-model="payment.reference">
                                    </div>
                                    <button type="button" class="self-end rounded-xl px-3 py-2.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30" @click="removePayment(index)">×</button>
                                    <div x-show="isCashPayment(payment) && paymentChange(payment) > 0" class="text-xs font-semibold text-emerald-700 dark:text-emerald-300 md:col-span-4">
                                        {{ __('ui.change') }}: <span x-text="money(paymentChange(payment))"></span>
                                    </div>
                                </div>
                            </template>

                            <div x-show="!payments.length" class="rounded-xl border border-dashed border-slate-300 p-5 text-center text-sm text-slate-400 dark:border-slate-700">
                                {{ __('ui.no_payment_credit_sale') }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="panel h-fit p-5 xl:sticky xl:top-4">
                <div class="space-y-3">
                    <div class="flex justify-between text-sm"><span>{{ __('ui.total') }}</span><strong x-text="money(total())"></strong></div>
                    <div class="flex justify-between text-sm"><span>{{ __('ui.payment_applied') }}</span><strong x-text="money(paymentAppliedTotal())"></strong></div>
                    <div class="flex justify-between text-sm text-emerald-700 dark:text-emerald-300"><span>{{ __('ui.change') }}</span><strong x-text="money(totalChange())"></strong></div>
                    <div class="flex justify-between border-t border-slate-200 pt-3 text-lg dark:border-slate-800">
                        <span class="font-black">{{ __('ui.credit_balance') }}</span>
                        <strong x-text="money(creditBalance())"></strong>
                    </div>
                </div>

                <div x-show="creditBalance() > 0" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
                    {{ __('ui.credit_customer_notice') }}
                </div>

                <button x-show="canCredit && selectedCustomer" class="btn-secondary mt-4 w-full" type="button" @click="payments=[]">
                    {{ __('ui.make_full_credit') }}
                </button>

                <button class="btn-primary mt-4 w-full py-4 text-base" type="button" @click="completeSale()" :disabled="submitting || (requiresOpenShift() && !hasOpenShift)">
                    <span x-show="!submitting">{{ __('ui.confirm_checkout') }}</span>
                    <span x-show="submitting">{{ __('ui.posting_sale') }}</span>
                    <kbd x-show="!submitting" class="ms-2 rounded-md bg-white/20 px-1.5 py-0.5 font-mono text-[10px]">Ctrl+Enter</kbd>
                </button>
            </aside>
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

                if (autoAdd && this.results.length === 1) {
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
            this.results = [];
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
