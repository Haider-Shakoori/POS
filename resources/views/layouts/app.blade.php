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
<div x-data="{ navOpen: false }" class="min-h-screen lg:grid lg:grid-cols-[17rem_minmax(0,1fr)]">
    <div x-show="navOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/60 lg:hidden" @click="navOpen = false"></div>

    <aside :class="navOpen ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full'" class="fixed inset-y-0 start-0 z-50 w-72 border-e border-slate-200 bg-white transition-transform lg:sticky lg:top-0 lg:z-auto lg:block lg:h-screen lg:w-auto lg:translate-x-0 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex h-full flex-col p-4">
            <div class="flex items-center justify-between gap-3 px-2 py-3">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="grid size-10 shrink-0 place-items-center rounded-2xl bg-brand-600 text-lg font-black text-white">P</div>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-bold">{{ $shop?->shop_name ?? config('app.name') }}</div>
                        <div class="text-xs text-slate-500">{{ __('ui.afghanistan_pos') }}</div>
                    </div>
                </div>
                <button type="button" class="btn-secondary px-3 lg:hidden" @click="navOpen = false">×</button>
            </div>

            <nav class="mt-5 space-y-1">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">
                    <span class="text-lg">⌂</span><span>{{ __('ui.dashboard') }}</span>
                </a>
                @if(auth()->user()->hasPermission('pos.access'))
                    <a href="{{ route('pos.index') }}" class="nav-link {{ request()->routeIs('pos.*') ? 'nav-link-active' : '' }}">
                        <span class="text-lg">▦</span><span>{{ __('ui.point_of_sale') }}</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('cash.view'))
                    <a href="{{ route('cash.index') }}" class="nav-link {{ request()->routeIs('cash.*') ? 'nav-link-active' : '' }}">
                        <span class="text-lg">¤</span><span>{{ __('ui.cash_drawer') }}</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('business_days.view'))
                    <a href="{{ route('closing.index') }}" class="nav-link {{ request()->routeIs('closing.*') ? 'nav-link-active' : '' }}">
                        <span class="text-lg">✓</span><span>{{ __('ui.daily_closing') }}</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('sales.view'))
                    <a href="{{ route('sales.index') }}" class="nav-link {{ request()->routeIs('sales.*') ? 'nav-link-active' : '' }}">
                        <span class="text-lg">≡</span><span>{{ __('ui.sales') }}</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('inventory.view'))
                    <a href="{{ route('inventory.products.index') }}" class="nav-link {{ request()->routeIs('inventory.products.*') ? 'nav-link-active' : '' }}">
                        <span class="text-lg">□</span><span>{{ __('ui.products') }}</span>
                    </a>
                    <a href="{{ route('inventory.operations.index') }}" class="nav-link {{ request()->routeIs('inventory.operations.*') || request()->routeIs('inventory.stock-counts.*') || request()->routeIs('inventory.writeoffs.*') ? 'nav-link-active' : '' }}">
                        <span class="text-lg">◎</span><span>{{ __('ui.inventory_operations') }}</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('purchases.view'))
                    <a href="{{ route('purchasing.orders.index') }}" class="nav-link {{ request()->routeIs('purchasing.orders.*') ? 'nav-link-active' : '' }}">
                        <span class="text-lg">⇣</span><span>{{ __('ui.purchase_orders') }}</span>
                    </a>
                    <a href="{{ route('purchasing.receipts.index') }}" class="nav-link {{ request()->routeIs('purchasing.receipts.*') ? 'nav-link-active' : '' }}">
                        <span class="text-lg">✓</span><span>{{ __('ui.goods_receipts') }}</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('suppliers.view'))
                    <a href="{{ route('purchasing.suppliers.index') }}" class="nav-link {{ request()->routeIs('purchasing.suppliers.*') ? 'nav-link-active' : '' }}">
                        <span class="text-lg">△</span><span>{{ __('ui.suppliers') }}</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('inventory.catalog.manage'))
                    <a href="{{ route('inventory.catalog.index') }}" class="nav-link {{ request()->routeIs('inventory.catalog.*') ? 'nav-link-active' : '' }}">
                        <span class="text-lg">◇</span><span>{{ __('ui.catalog_setup') }}</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('customers.view'))
                    <a href="{{ route('customers.index') }}" class="nav-link {{ request()->routeIs('customers.*') ? 'nav-link-active' : '' }}">
                        <span class="text-lg">◇</span><span>{{ __('ui.customers') }}</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('reports.view'))
                    <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'nav-link-active' : '' }}">
                        <span class="text-lg">◌</span><span>{{ __('ui.reports') }}</span>
                    </a>
                @endif
                @if(auth()->user()->hasPermission('settings.manage'))
                    <a href="{{ route('settings.shop.edit') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'nav-link-active' : '' }}">
                        <span class="text-lg">⚙</span><span>{{ __('ui.shop_settings') }}</span>
                    </a>
                @endif
            </nav>

            <div class="mt-auto panel p-3">
                <div class="text-sm font-semibold">{{ auth()->user()->name }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ auth()->user()->username }}</div>
                <form class="mt-3" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn-secondary w-full" type="submit">{{ __('ui.logout') }}</button>
                </form>
            </div>
        </div>
    </aside>

    <main class="min-w-0">
        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90">
            <div class="flex min-h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" class="btn-secondary px-3 lg:hidden" @click="navOpen = true" aria-label="{{ __('ui.open_navigation') }}">☰</button>
                    <div class="min-w-0">
                        <div class="truncate text-xs font-medium text-slate-500">{{ now()->format('l, d M Y') }}</div>
                        <h1 class="truncate text-base font-bold">@yield('page-title', __('ui.dashboard'))</h1>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('locale.update', app()->getLocale() === 'en' ? 'fa' : (app()->getLocale() === 'fa' ? 'ps' : 'en')) }}">
                        @csrf
                        <button class="btn-secondary" type="submit">{{ __('ui.language') }}</button>
                    </form>
                    <button class="btn-secondary px-3" type="button" @click="$store.theme.toggle()" aria-label="{{ __('ui.toggle_theme') }}">◐</button>
                </div>
            </div>
        </header>

        <div class="p-4 sm:p-6 lg:p-8">
            @if(session('status'))
                <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                    {{ session('status') }}
                </div>
            @endif
            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
