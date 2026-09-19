@extends('layouts.app')

@section('title', __('ui.point_of_sale'))
@section('page-title', __('ui.point_of_sale'))

@section('content')
<div class="grid min-h-[calc(100vh-9rem)] gap-4 xl:grid-cols-[minmax(0,1fr)_25rem]">
    <section class="panel flex min-h-[32rem] flex-col overflow-hidden">
        <div class="border-b border-slate-200 p-4 dark:border-slate-800">
            <div class="relative">
                <input class="field py-3 ps-11" disabled placeholder="{{ __('ui.scan_search_placeholder') }}">
                <span class="absolute start-4 top-1/2 -translate-y-1/2 text-slate-400">⌕</span>
            </div>
        </div>
        <div class="grid flex-1 place-items-center p-8 text-center">
            <div class="max-w-md">
                <div class="mx-auto grid size-16 place-items-center rounded-3xl bg-brand-50 text-3xl text-brand-700 dark:bg-brand-950/60 dark:text-brand-300">▦</div>
                <h2 class="mt-5 text-2xl font-black">{{ __('ui.pos_shell_ready') }}</h2>
                <p class="mt-3 text-sm leading-6 text-slate-500">{{ __('ui.pos_shell_message') }}</p>
            </div>
        </div>
    </section>

    <aside class="panel flex flex-col">
        <div class="border-b border-slate-200 p-5 dark:border-slate-800">
            <div class="flex items-center justify-between">
                <h3 class="font-black">{{ __('ui.current_sale') }}</h3>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500 dark:bg-slate-800">0 {{ __('ui.items') }}</span>
            </div>
        </div>
        <div class="grid flex-1 place-items-center p-6 text-center text-sm text-slate-400">{{ __('ui.cart_empty') }}</div>
        <div class="space-y-3 border-t border-slate-200 p-5 dark:border-slate-800">
            <div class="flex justify-between text-sm"><span>{{ __('ui.subtotal') }}</span><strong>؋ 0.00</strong></div>
            <div class="flex justify-between text-sm"><span>{{ __('ui.discount') }}</span><strong>؋ 0.00</strong></div>
            <div class="flex justify-between border-t border-slate-200 pt-3 text-xl dark:border-slate-800"><span class="font-black">{{ __('ui.total') }}</span><strong>؋ 0.00</strong></div>
            <button class="btn-primary w-full py-3.5" disabled>{{ __('ui.pay') }}</button>
        </div>
    </aside>
</div>
@endsection
