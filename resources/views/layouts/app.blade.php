<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('pos.locales.'.app()->getLocale().'.direction', 'ltr') }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', __('ui.dashboard')) · {{ config('app.name') }}</title>
    <script>
        if (localStorage.getItem('pos-theme') === 'dark') document.documentElement.classList.add('dark');
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-100">
@php($currentUser = auth()->user())
<div x-data="{ navOpen: false }" class="min-h-screen lg:grid lg:grid-cols-[17.5rem_minmax(0,1fr)]">
    <div x-cloak x-show="navOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm lg:hidden" @click="navOpen = false"></div>

    <aside
        :class="navOpen ? 'app-sidebar-open' : 'app-sidebar-closed'"
        class="app-sidebar fixed inset-y-0 start-0 z-50 w-[17.5rem] border-e border-slate-200 bg-white/95 shadow-2xl shadow-slate-950/5 backdrop-blur transition-transform duration-200 lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:shadow-none dark:border-slate-800 dark:bg-slate-900/95"
    >
        <div class="flex h-full min-h-0 flex-col">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-5 dark:border-slate-800">
                <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3">
                    <div class="grid size-11 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-lg font-black text-white shadow-lg shadow-brand-600/20">P</div>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-black">{{ $shop?->shop_name ?? config('app.name') }}</div>
                        <div class="mt-0.5 truncate text-[11px] font-medium uppercase tracking-wider text-slate-400">{{ __('ui.afghanistan_pos') }}</div>
                    </div>
                </a>
                <button type="button" class="btn-icon lg:hidden" @click="navOpen = false" aria-label="{{ __('ui.close_navigation') }}">×</button>
            </div>

            <nav class="min-h-0 flex-1 overflow-y-auto px-3 py-4">
                <div class="nav-group-title">{{ __('ui.workspace') }}</div>
                <div class="space-y-1">
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">
                        <x-nav-icon name="dashboard" /><span>{{ __('ui.dashboard') }}</span>
                    </a>
                    @if($currentUser->hasPermission('pos.access'))
                        <a href="{{ route('pos.index') }}" class="nav-link {{ request()->routeIs('pos.*') ? 'nav-link-active' : '' }}">
                            <x-nav-icon name="pos" /><span>{{ __('ui.point_of_sale') }}</span>
                        </a>
                    @endif
                </div>

                <div class="nav-group-title mt-5">{{ __('ui.operations') }}</div>
                <div class="space-y-1">
                    @if($currentUser->hasPermission('sales.view'))
                        <a href="{{ route('sales.index') }}" class="nav-link {{ request()->routeIs('sales.*') ? 'nav-link-active' : '' }}">
                            <x-nav-icon name="sales" /><span>{{ __('ui.sales') }}</span>
                        </a>
                    @endif
                    @if($currentUser->hasPermission('customers.view'))
                        <a href="{{ route('customers.index') }}" class="nav-link {{ request()->routeIs('customers.*') ? 'nav-link-active' : '' }}">
                            <x-nav-icon name="customers" /><span>{{ __('ui.customers') }}</span>
                        </a>
                    @endif
                    @if($currentUser->hasPermission('inventory.view'))
                        <a href="{{ route('inventory.products.index') }}" class="nav-link {{ request()->routeIs('inventory.products.*') ? 'nav-link-active' : '' }}">
                            <x-nav-icon name="products" /><span>{{ __('ui.products') }}</span>
                        </a>
                        <a href="{{ route('inventory.operations.index') }}" class="nav-link {{ request()->routeIs('inventory.operations.*') || request()->routeIs('inventory.stock-counts.*') || request()->routeIs('inventory.writeoffs.*') ? 'nav-link-active' : '' }}">
                            <x-nav-icon name="inventory" /><span>{{ __('ui.inventory_operations') }}</span>
                        </a>
                    @endif
                    @if($currentUser->hasPermission('purchases.view'))
                        <a href="{{ route('purchasing.orders.index') }}" class="nav-link {{ request()->routeIs('purchasing.orders.*') ? 'nav-link-active' : '' }}">
                            <x-nav-icon name="purchase" /><span>{{ __('ui.purchase_orders') }}</span>
                        </a>
                        <a href="{{ route('purchasing.receipts.index') }}" class="nav-link {{ request()->routeIs('purchasing.receipts.*') ? 'nav-link-active' : '' }}">
                            <x-nav-icon name="receipt" /><span>{{ __('ui.goods_receipts') }}</span>
                        </a>
                    @endif
                    @if($currentUser->hasPermission('suppliers.view'))
                        <a href="{{ route('purchasing.suppliers.index') }}" class="nav-link {{ request()->routeIs('purchasing.suppliers.*') ? 'nav-link-active' : '' }}">
                            <x-nav-icon name="supplier" /><span>{{ __('ui.suppliers') }}</span>
                        </a>
                    @endif
                    @if($currentUser->hasPermission('expenses.view'))
                        <a href="{{ route('expenses.index') }}" class="nav-link {{ request()->routeIs('expenses.*') ? 'nav-link-active' : '' }}">
                            <x-nav-icon name="expenses" /><span>{{ __('ui.operating_entries') }}</span>
                        </a>
                    @endif
                    @if($currentUser->hasPermission('cash.view'))
                        <a href="{{ route('cash.index') }}" class="nav-link {{ request()->routeIs('cash.*') ? 'nav-link-active' : '' }}">
                            <x-nav-icon name="cash" /><span>{{ __('ui.cash_drawer') }}</span>
                        </a>
                    @endif
                    @if($currentUser->hasPermission('business_days.view'))
                        <a href="{{ route('closing.index') }}" class="nav-link {{ request()->routeIs('closing.*') ? 'nav-link-active' : '' }}">
                            <x-nav-icon name="closing" /><span>{{ __('ui.daily_closing') }}</span>
                        </a>
                    @endif
                    @if($currentUser->hasPermission('inventory.catalog.manage'))
                        <a href="{{ route('inventory.catalog.index') }}" class="nav-link {{ request()->routeIs('inventory.catalog.*') ? 'nav-link-active' : '' }}">
                            <x-nav-icon name="settings" /><span>{{ __('ui.catalog_setup') }}</span>
                        </a>
                    @endif
                </div>

                @if($currentUser->hasPermission('reports.view'))
                    <div class="nav-group-title mt-5">{{ __('ui.insights') }}</div>
                    <div class="space-y-1">
                        <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'nav-link-active' : '' }}">
                            <x-nav-icon name="reports" /><span>{{ __('ui.reports') }}</span>
                        </a>
                    </div>
                @endif

                @if($currentUser->hasPermission('users.manage') || $currentUser->hasPermission('audit.view') || $currentUser->hasPermission('settings.manage'))
                    <div class="nav-group-title mt-5">{{ __('ui.administration') }}</div>
                    <div class="space-y-1">
                        @if($currentUser->hasPermission('users.manage'))
                            <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'nav-link-active' : '' }}">
                                <x-nav-icon name="users" /><span>{{ __('ui.users_access') }}</span>
                            </a>
                        @endif
                        @if($currentUser->hasRole('owner'))
                            <a href="{{ route('admin.roles.index') }}" class="nav-link {{ request()->routeIs('admin.roles.*') ? 'nav-link-active' : '' }}">
                                <x-nav-icon name="roles" /><span>{{ __('ui.roles_permissions') }}</span>
                            </a>
                        @endif
                        @if($currentUser->hasPermission('audit.view'))
                            <a href="{{ route('admin.audit.index') }}" class="nav-link {{ request()->routeIs('admin.audit.*') ? 'nav-link-active' : '' }}">
                                <x-nav-icon name="audit" /><span>{{ __('ui.audit_log') }}</span>
                            </a>
                        @endif
                        @if($currentUser->hasPermission('settings.manage'))
                            <a href="{{ route('settings.terminals.index') }}" class="nav-link {{ request()->routeIs('settings.terminals.*') ? 'nav-link-active' : '' }}">
                                <x-nav-icon name="terminal" /><span>{{ __('ui.terminals') }}</span>
                            </a>
                            <a href="{{ route('settings.shop.edit') }}" class="nav-link {{ request()->routeIs('settings.shop.*') ? 'nav-link-active' : '' }}">
                                <x-nav-icon name="settings" /><span>{{ __('ui.shop_settings') }}</span>
                            </a>
                        @endif
                    </div>
                @endif
            </nav>

            <div class="border-t border-slate-100 p-3 dark:border-slate-800">
                <div class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-950/50">
                    <div class="flex items-center gap-3">
                        <div class="grid size-9 shrink-0 place-items-center rounded-xl bg-slate-900 text-xs font-black text-white dark:bg-slate-700">{{ mb_strtoupper(mb_substr($currentUser->name, 0, 1)) }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-bold">{{ $currentUser->name }}</div>
                            <div class="truncate text-xs text-slate-500">{{ '@'.$currentUser->username }}</div>
                        </div>
                    </div>
                    <form class="mt-3" method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn-secondary w-full py-2" type="submit">{{ __('ui.logout') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    <main class="min-w-0">
        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/85 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/85">
            <div class="flex min-h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" class="btn-icon lg:hidden" @click="navOpen = true" aria-label="{{ __('ui.open_navigation') }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </button>
                    <div class="min-w-0">
                        <div class="truncate text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ now()->format('l, d M Y') }}</div>
                        <h1 class="truncate text-base font-black">@yield('page-title', __('ui.dashboard'))</h1>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="relative" x-data="{ languageOpen: false }" @click.outside="languageOpen = false">
                        <button
                            class="btn-secondary px-3"
                            type="button"
                            @click="languageOpen = !languageOpen"
                            :aria-expanded="languageOpen"
                            aria-haspopup="menu"
                        >
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>
                            </svg>
                            <span class="hidden sm:inline">{{ config('pos.locales.'.app()->getLocale().'.label') }}</span>
                            <svg class="size-4 transition" :class="languageOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>

                        <div
                            x-cloak
                            x-show="languageOpen"
                            x-transition.origin.top.right
                            class="absolute end-0 z-50 mt-2 w-48 overflow-hidden rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl shadow-slate-950/10 dark:border-slate-700 dark:bg-slate-900"
                            role="menu"
                        >
                            @foreach(config('pos.locales') as $localeCode => $locale)
                                <form method="POST" action="{{ route('locale.update', $localeCode) }}">
                                    @csrf
                                    <button
                                        class="flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-start text-sm font-semibold transition hover:bg-slate-50 dark:hover:bg-slate-800 {{ app()->getLocale() === $localeCode ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/50 dark:text-brand-300' : 'text-slate-700 dark:text-slate-200' }}"
                                        type="submit"
                                        role="menuitem"
                                    >
                                        <span>{{ $locale['label'] }}</span>
                                        @if(app()->getLocale() === $localeCode)
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path d="m5 12 4 4L19 6"/>
                                            </svg>
                                        @endif
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                    <button
                        class="btn-secondary gap-2 px-3"
                        type="button"
                        title="{{ __('ui.global_search') }}"
                        @click="window.dispatchEvent(new CustomEvent('open-global-search'))"
                    >
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <span class="hidden xl:inline text-xs font-bold text-slate-400">{{ __('ui.global_search') }}</span>
                        <kbd class="hidden xl:inline rounded-md border border-slate-200 bg-slate-50 px-1.5 py-0.5 font-mono text-[10px] font-bold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">Ctrl K</kbd>
                    </button>
                    <button class="btn-icon" type="button" @click="$store.theme.toggle()" aria-label="{{ __('ui.toggle_theme') }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3a9 9 0 1 0 9 9c0-.5 0-1-.1-1.5A7 7 0 0 1 12 3Z"/></svg>
                    </button>
                </div>
            </div>
        </header>

        <div class="mx-auto w-full max-w-[1600px] p-4 sm:p-6 lg:p-8">
            @if(session('status'))
                <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 shadow-sm dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                    {{ session('status') }}
                </div>
            @endif
            @unless(\Illuminate\Support\Facades\View::hasSection('page-errors'))
                @if($errors->any())
                    <div class="alert-error mb-5">{{ $errors->first() }}</div>
                @endif
            @endunless
            @yield('content')
        </div>
    </main>
</div>

<div
    x-data="{
        open: false,
        query: '',
        loading: false,
        results: [],
        failed: false,
        searchUrl: @js(route('search')),
        typeLabels: {
            product: @js(__('ui.products')),
            customer: @js(__('ui.customers')),
            supplier: @js(__('ui.suppliers')),
            sale: @js(__('ui.sales')),
        },
        init() {
            window.addEventListener('keydown', (event) => {
                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                    event.preventDefault();
                    this.openPalette();
                }

                if (event.key === 'Escape' && this.open) {
                    this.open = false;
                }
            });

            window.addEventListener('open-global-search', () => this.openPalette());
        },
        openPalette() {
            this.open = true;
            this.$nextTick(() => this.$refs.paletteInput?.focus());
        },
        async run() {
            this.failed = false;

            if (this.query.trim().length < 2) {
                this.results = [];
                return;
            }

            this.loading = true;

            try {
                const response = await fetch(this.searchUrl + '?q=' + encodeURIComponent(this.query), {
                    headers: { 'Accept': 'application/json' },
                });

                if (!response.ok) throw new Error('search failed');

                const payload = await response.json();
                this.results = payload.data || [];
            } catch (error) {
                this.failed = true;
                this.results = [];
            } finally {
                this.loading = false;
            }
        },
        goFirst() {
            if (this.results.length) window.location.href = this.results[0].url;
        },
    }"
    x-cloak
    x-show="open"
    x-transition.opacity
    class="fixed inset-0 z-[110] overflow-y-auto bg-slate-950/70 p-4 backdrop-blur-sm"
    @keydown.escape.window="open = false"
>
    <div class="mx-auto mt-20 max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900" @click.outside="open = false">
        <div class="flex items-center gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800">
            <svg class="size-5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input
                x-ref="paletteInput"
                x-model="query"
                @input.debounce.250ms="run()"
                @keydown.enter.prevent="goFirst()"
                class="w-full border-0 bg-transparent p-0 text-sm font-semibold outline-none focus:ring-0"
                placeholder="{{ __('ui.global_search_placeholder') }}"
                autocomplete="off"
            >
            <span x-show="loading" class="text-xs text-slate-400">{{ __('ui.searching') }}</span>
            <button type="button" class="btn-secondary px-2 py-1 text-xs" @click="open = false">Esc</button>
        </div>

        <div class="max-h-96 overflow-y-auto">
            <template x-if="failed">
                <div class="px-4 py-8 text-center text-sm text-red-600">{{ __('ui.search_failed') }}</div>
            </template>

            <template x-if="!failed && query.trim().length >= 2 && !loading && !results.length">
                <div class="px-4 py-8 text-center text-sm text-slate-400">{{ __('ui.no_results') }}</div>
            </template>

            <template x-for="result in results" :key="result.type + ':' + result.url">
                <a :href="result.url" class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-start transition last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-bold" x-text="result.label"></div>
                        <div class="mt-0.5 truncate text-xs text-slate-500" x-text="result.meta || '—'"></div>
                    </div>
                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800" x-text="typeLabels[result.type] || result.type"></span>
                </a>
            </template>

            <div x-show="query.trim().length < 2" class="px-4 py-8 text-center text-xs text-slate-400">
                {{ __('ui.global_search_hint') }}
            </div>
        </div>
    </div>
</div>
</body>
</html>
