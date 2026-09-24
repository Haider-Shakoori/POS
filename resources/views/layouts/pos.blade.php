<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('pos.locales.'.app()->getLocale().'.direction', 'ltr') }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', __('ui.point_of_sale')) · {{ config('app.name') }}</title>
    <script>
        if (localStorage.getItem('pos-theme') === 'dark') document.documentElement.classList.add('dark');
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full overflow-hidden bg-slate-100 text-slate-950 dark:bg-slate-950 dark:text-slate-100">
@php($currentUser = auth()->user())
<div
    x-data="{ appMenuOpen: false }"
    @open-pos-menu.window="appMenuOpen = true"
    @keydown.escape.window="appMenuOpen = false"
    class="h-screen overflow-hidden"
>
    @yield('content')

    <div
        x-cloak
        x-show="appMenuOpen"
        x-transition.opacity
        class="fixed inset-0 z-[120] overflow-y-auto bg-slate-950/75 p-3 backdrop-blur-md sm:p-5"
        @click.self="appMenuOpen = false"
    >
        <div class="mx-auto my-2 max-w-6xl overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900 sm:my-6">
            <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6 dark:border-slate-800">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="grid size-11 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-lg font-black text-white shadow-lg shadow-brand-600/20">P</div>
                    <div class="min-w-0">
                        <div class="truncate font-black">{{ $shop?->shop_name ?? config('app.name') }}</div>
                        <div class="mt-0.5 text-xs text-slate-500">{{ __('ui.open_navigation') }}</div>
                    </div>
                </div>
                <button type="button" class="btn-icon" @click="appMenuOpen = false" aria-label="{{ __('ui.close_navigation') }}">×</button>
            </div>

            <div class="max-h-[calc(100vh-8rem)] overflow-y-auto p-4 sm:p-6">
                <div class="grid gap-5 lg:grid-cols-3">
                    <section>
                        <div class="nav-group-title">{{ __('ui.workspace') }}</div>
                        <div class="grid gap-2">
                            <a href="{{ route('dashboard') }}" class="nav-link"><x-nav-icon name="dashboard" /><span>{{ __('ui.dashboard') }}</span></a>
                            @if($currentUser->hasPermission('pos.access'))
                                <a href="{{ route('pos.index') }}" class="nav-link nav-link-active"><x-nav-icon name="pos" /><span>{{ __('ui.point_of_sale') }}</span></a>
                            @endif
                        </div>
                    </section>

                    <section class="lg:col-span-2">
                        <div class="nav-group-title">{{ __('ui.operations') }}</div>
                        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                            @if($currentUser->hasPermission('sales.view'))
                                <a href="{{ route('sales.index') }}" class="nav-link"><x-nav-icon name="sales" /><span>{{ __('ui.sales') }}</span></a>
                            @endif
                            @if($currentUser->hasPermission('customers.view'))
                                <a href="{{ route('customers.index') }}" class="nav-link"><x-nav-icon name="customers" /><span>{{ __('ui.customers') }}</span></a>
                            @endif
                            @if($currentUser->hasPermission('inventory.view'))
                                <a href="{{ route('inventory.products.index') }}" class="nav-link"><x-nav-icon name="products" /><span>{{ __('ui.products') }}</span></a>
                                <a href="{{ route('inventory.operations.index') }}" class="nav-link"><x-nav-icon name="inventory" /><span>{{ __('ui.inventory_operations') }}</span></a>
                            @endif
                            @if($currentUser->hasPermission('purchases.view'))
                                <a href="{{ route('purchasing.orders.index') }}" class="nav-link"><x-nav-icon name="purchase" /><span>{{ __('ui.purchase_orders') }}</span></a>
                                <a href="{{ route('purchasing.receipts.index') }}" class="nav-link"><x-nav-icon name="receipt" /><span>{{ __('ui.goods_receipts') }}</span></a>
                            @endif
                            @if($currentUser->hasPermission('suppliers.view'))
                                <a href="{{ route('purchasing.suppliers.index') }}" class="nav-link"><x-nav-icon name="supplier" /><span>{{ __('ui.suppliers') }}</span></a>
                            @endif
                            @if($currentUser->hasPermission('expenses.view'))
                                <a href="{{ route('expenses.index') }}" class="nav-link"><x-nav-icon name="expenses" /><span>{{ __('ui.operating_entries') }}</span></a>
                            @endif
                            @if($currentUser->hasPermission('cash.view'))
                                <a href="{{ route('cash.index') }}" class="nav-link"><x-nav-icon name="cash" /><span>{{ __('ui.cash_drawer') }}</span></a>
                            @endif
                            @if($currentUser->hasPermission('business_days.view'))
                                <a href="{{ route('closing.index') }}" class="nav-link"><x-nav-icon name="closing" /><span>{{ __('ui.daily_closing') }}</span></a>
                            @endif
                            @if($currentUser->hasPermission('inventory.catalog.manage'))
                                <a href="{{ route('inventory.catalog.index') }}" class="nav-link"><x-nav-icon name="settings" /><span>{{ __('ui.catalog_setup') }}</span></a>
                            @endif
                            @if($currentUser->hasPermission('reports.view'))
                                <a href="{{ route('reports.index') }}" class="nav-link"><x-nav-icon name="reports" /><span>{{ __('ui.reports') }}</span></a>
                            @endif
                        </div>
                    </section>

                    @if($currentUser->hasPermission('users.manage') || $currentUser->hasPermission('audit.view') || $currentUser->hasPermission('settings.manage') || $currentUser->hasRole('owner'))
                        <section class="lg:col-span-2">
                            <div class="nav-group-title">{{ __('ui.administration') }}</div>
                            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                @if($currentUser->hasPermission('users.manage'))
                                    <a href="{{ route('admin.users.index') }}" class="nav-link"><x-nav-icon name="users" /><span>{{ __('ui.users_access') }}</span></a>
                                @endif
                                @if($currentUser->hasRole('owner'))
                                    <a href="{{ route('admin.roles.index') }}" class="nav-link"><x-nav-icon name="roles" /><span>{{ __('ui.roles_permissions') }}</span></a>
                                @endif
                                @if($currentUser->hasPermission('audit.view'))
                                    <a href="{{ route('admin.audit.index') }}" class="nav-link"><x-nav-icon name="audit" /><span>{{ __('ui.audit_log') }}</span></a>
                                @endif
                                @if($currentUser->hasPermission('settings.manage'))
                                    <a href="{{ route('settings.terminals.index') }}" class="nav-link"><x-nav-icon name="terminal" /><span>{{ __('ui.terminals') }}</span></a>
                                    <a href="{{ route('settings.shop.edit') }}" class="nav-link"><x-nav-icon name="settings" /><span>{{ __('ui.shop_settings') }}</span></a>
                                @endif
                            </div>
                        </section>
                    @endif

                    <section>
                        <div class="nav-group-title">{{ __('ui.language') }}</div>
                        <div class="grid gap-2">
                            @foreach(config('pos.locales') as $localeCode => $locale)
                                <form method="POST" action="{{ route('locale.update', $localeCode) }}">
                                    @csrf
                                    <button class="nav-link w-full {{ app()->getLocale() === $localeCode ? 'nav-link-active' : '' }}" type="submit">
                                        <span class="grid size-7 place-items-center rounded-lg bg-slate-100 text-[10px] font-black uppercase dark:bg-slate-800">{{ $localeCode }}</span>
                                        <span>{{ $locale['label'] }}</span>
                                    </button>
                                </form>
                            @endforeach
                            <button class="nav-link w-full" type="button" @click="$store.theme.toggle()">
                                <span class="grid size-7 place-items-center rounded-lg bg-slate-100 dark:bg-slate-800">◐</span>
                                <span>{{ __('ui.toggle_theme') }}</span>
                            </button>
                        </div>
                    </section>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-slate-800 dark:bg-slate-950/50">
                <div class="min-w-0">
                    <div class="truncate text-sm font-black">{{ $currentUser->name }}</div>
                    <div class="truncate text-xs text-slate-500">{{ '@'.$currentUser->username }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn-secondary w-full sm:w-auto" type="submit">{{ __('ui.logout') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
