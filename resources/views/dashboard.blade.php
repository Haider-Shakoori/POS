@extends('layouts.app')

@section('title', __('ui.dashboard'))
@section('page-title', __('ui.dashboard'))

@section('content')
<div class="space-y-6">
    <section class="overflow-hidden rounded-3xl bg-slate-950 p-6 text-white sm:p-8 dark:ring-1 dark:ring-slate-800">
        <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
            <div>
                <div class="text-sm font-semibold text-brand-300">{{ __('ui.foundation_ready') }}</div>
                <h2 class="mt-2 text-3xl font-black">{{ __('ui.welcome_back') }}, {{ auth()->user()->name }}</h2>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">{{ __('ui.foundation_message') }}</p>
            </div>
            @if(auth()->user()->hasPermission('pos.access'))
                <a href="{{ route('pos.index') }}" class="btn-primary shrink-0">{{ __('ui.open_pos') }}</a>
            @endif
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.currency') }}</div>
            <div class="mt-3 text-2xl font-black">{{ $currencyCode }}</div>
            <div class="mt-1 text-sm text-slate-500">{{ __('ui.afn_only') }}</div>
        </article>
        <article class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.sales_tax') }}</div>
            <div class="mt-3 text-2xl font-black">{{ __('ui.none') }}</div>
            <div class="mt-1 text-sm text-slate-500">{{ __('ui.no_sales_tax') }}</div>
        </article>
        <article class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.current_shift') }}</div>
            <div class="mt-3 text-2xl font-black">{{ $openShift ? __('ui.open') : __('ui.not_open') }}</div>
            <div class="mt-1 text-sm text-slate-500">{{ __('ui.daily_closing_ready') }}</div>
        </article>
        <article class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('ui.language') }}</div>
            <div class="mt-3 text-2xl font-black">{{ config('pos.locales.'.app()->getLocale().'.label') }}</div>
            <div class="mt-1 text-sm text-slate-500">LTR / RTL</div>
        </article>
    </section>

    <section class="grid gap-4 xl:grid-cols-2">
        <div class="panel p-6">
            <h3 class="text-lg font-black">{{ __('ui.foundation_modules') }}</h3>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                @foreach([
                    __('ui.roles_permissions'),
                    __('ui.audit_logging'),
                    __('ui.shop_settings'),
                    __('ui.terminal_shifts'),
                    __('ui.localization'),
                    __('ui.money_rules'),
                ] as $item)
                    <div class="rounded-xl border border-slate-200 p-3 text-sm font-semibold dark:border-slate-800">✓ {{ $item }}</div>
                @endforeach
            </div>
        </div>

        <div class="panel p-6">
            <h3 class="text-lg font-black">{{ __('ui.next_batch') }}</h3>
            <p class="mt-3 text-sm leading-6 text-slate-500">{{ __('ui.next_batch_message') }}</p>
            <div class="mt-5 rounded-2xl bg-brand-50 p-4 text-sm font-semibold text-brand-800 dark:bg-brand-950/50 dark:text-brand-200">
                {{ __('ui.inventory_next') }}
            </div>
        </div>
    </section>
</div>
@endsection
