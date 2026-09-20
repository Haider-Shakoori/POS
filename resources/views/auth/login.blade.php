@extends('layouts.guest')

@section('content')
<div class="grid min-h-screen lg:grid-cols-[1.05fr_.95fr]">
    <section class="hidden bg-slate-950 p-10 text-white lg:flex lg:flex-col lg:justify-between">
        <div class="flex items-center gap-3">
            <div class="grid size-12 place-items-center rounded-2xl bg-brand-500 text-xl font-black">P</div>
            <div>
                <div class="font-bold">{{ config('app.name') }}</div>
                <div class="text-sm text-slate-400">{{ __('ui.afghanistan_pos') }}</div>
            </div>
        </div>
        <div class="max-w-xl">
            <div class="mb-5 inline-flex rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-brand-300">{{ __('ui.afn_only') }} · {{ __('ui.no_sales_tax') }}</div>
            <h1 class="text-5xl font-black leading-tight">{{ __('ui.login_hero') }}</h1>
            <p class="mt-5 max-w-lg text-base leading-7 text-slate-300">{{ __('ui.login_hero_subtitle') }}</p>
        </div>
        <div class="text-sm text-slate-500">English · دری · پښتو</div>
    </section>

    <section class="flex items-center justify-center p-6 sm:p-10">
        <div class="w-full max-w-md">
            <div class="mb-8 lg:hidden">
                <div class="text-2xl font-black">{{ config('app.name') }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ __('ui.afghanistan_pos') }}</div>
            </div>

            <div class="panel p-6 sm:p-8">
                @if(session('status'))
                    <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                        {{ session('status') }}
                    </div>
                @endif

                <h2 class="text-2xl font-black">{{ __('ui.sign_in') }}</h2>
                <p class="mt-2 text-sm text-slate-500">{{ __('ui.sign_in_help') }}</p>

                <form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-5">
                    @csrf
                    <div>
                        <label for="username" class="mb-2 block text-sm font-semibold">{{ __('ui.username') }}</label>
                        <input id="username" name="username" value="{{ old('username') }}" class="field" autocomplete="username" autofocus required>
                        @error('username')<p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-semibold">{{ __('ui.password') }}</label>
                        <input id="password" type="password" name="password" class="field" autocomplete="current-password" required>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300 text-brand-600">
                        <span>{{ __('ui.remember_me') }}</span>
                    </label>

                    <button class="btn-primary w-full" type="submit">{{ __('ui.sign_in') }}</button>
                </form>

                <div class="mt-6 flex justify-center">
                    <div class="relative" x-data="{ languageOpen: false }" @click.outside="languageOpen = false">
                        <button class="btn-secondary min-w-44 justify-between" type="button" @click="languageOpen = !languageOpen" aria-haspopup="menu" :aria-expanded="languageOpen">
                            <span class="inline-flex items-center gap-2">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <circle cx="12" cy="12" r="9"/>
                                    <path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>
                                </svg>
                                {{ config('pos.locales.'.app()->getLocale().'.label') }}
                            </span>
                            <svg class="size-4 transition" :class="languageOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>

                        <div x-cloak x-show="languageOpen" x-transition class="absolute bottom-full start-1/2 z-50 mb-2 w-48 -translate-x-1/2 overflow-hidden rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-slate-700 dark:bg-slate-900" role="menu">
                            @foreach(config('pos.locales') as $code => $locale)
                                <form method="POST" action="{{ route('locale.update', $code) }}">
                                    @csrf
                                    <button type="submit" role="menuitem" class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-start text-sm font-semibold transition hover:bg-slate-50 dark:hover:bg-slate-800 {{ app()->getLocale() === $code ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/50 dark:text-brand-300' : 'text-slate-700 dark:text-slate-200' }}">
                                        <span>{{ $locale['label'] }}</span>
                                        @if(app()->getLocale() === $code)
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                                        @endif
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
