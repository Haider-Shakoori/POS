@extends('layouts.guest')

@section('title', __('ui.initial_setup'))

@section('content')
<div class="min-h-screen bg-slate-50 dark:bg-slate-950">
    <div class="mx-auto flex min-h-screen max-w-7xl items-center justify-center p-5 sm:p-8">
        <div class="grid w-full overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-2xl shadow-slate-950/10 lg:grid-cols-[.9fr_1.1fr] dark:border-slate-800 dark:bg-slate-900">
            <section class="relative overflow-hidden bg-slate-950 p-7 text-white sm:p-10">
                <div class="absolute -end-20 -top-20 size-72 rounded-full bg-brand-500/20 blur-3xl"></div>
                <div class="absolute -bottom-20 start-0 size-60 rounded-full bg-cyan-400/10 blur-3xl"></div>

                <div class="relative flex h-full min-h-[30rem] flex-col justify-between">
                    <div class="flex items-center gap-3">
                        <div class="grid size-12 place-items-center rounded-2xl bg-brand-500 text-xl font-black">P</div>
                        <div>
                            <div class="font-black">{{ config('app.name') }}</div>
                            <div class="text-sm text-slate-400">{{ __('ui.afghanistan_pos') }}</div>
                        </div>
                    </div>

                    <div class="my-12">
                        <span class="inline-flex rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-bold text-emerald-300 ring-1 ring-inset ring-emerald-400/20">
                            {{ __('ui.first_run') }}
                        </span>
                        <h1 class="mt-5 text-4xl font-black leading-tight sm:text-5xl">{{ __('ui.setup_hero') }}</h1>
                        <p class="mt-5 max-w-xl text-sm leading-7 text-slate-300">{{ __('ui.setup_hero_help') }}</p>
                    </div>

                    <div class="grid gap-3 text-sm text-slate-300 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                        <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-inset ring-white/10">
                            <div class="font-bold text-white">{{ __('ui.owner_account') }}</div>
                            <div class="mt-1 text-xs leading-5 text-slate-400">{{ __('ui.owner_account_help') }}</div>
                        </div>
                        <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-inset ring-white/10">
                            <div class="font-bold text-white">{{ __('ui.reference_data') }}</div>
                            <div class="mt-1 text-xs leading-5 text-slate-400">{{ __('ui.reference_data_help') }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="p-6 sm:p-10 lg:p-12">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="eyebrow">{{ __('ui.initial_setup') }}</p>
                        <h2 class="text-2xl font-black">{{ __('ui.create_initial_owner') }}</h2>
                        <p class="mt-2 max-w-xl text-sm leading-6 text-slate-500">{{ __('ui.create_initial_owner_help') }}</p>
                    </div>

                    <div class="relative shrink-0" x-data="{ languageOpen: false }" @click.outside="languageOpen = false">
                        <button class="btn-secondary px-3" type="button" @click="languageOpen = !languageOpen" aria-haspopup="menu" :aria-expanded="languageOpen">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>
                            </svg>
                            <span>{{ config('pos.locales.'.app()->getLocale().'.label') }}</span>
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div x-cloak x-show="languageOpen" x-transition class="absolute end-0 z-50 mt-2 w-44 overflow-hidden rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                            @foreach(config('pos.locales') as $code => $locale)
                                <form method="POST" action="{{ route('locale.update', $code) }}">
                                    @csrf
                                    <button type="submit" class="w-full rounded-xl px-3 py-2.5 text-start text-sm font-semibold transition hover:bg-slate-50 dark:hover:bg-slate-800 {{ app()->getLocale() === $code ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/50 dark:text-brand-300' : '' }}">
                                        {{ $locale['label'] }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if($errors->any())
                    <div class="alert-error mt-6">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('setup.store') }}" class="mt-7 space-y-5">
                    @csrf

                    <div>
                        <label class="field-label">{{ __('ui.owner_name') }}</label>
                        <input class="field" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="field-label">{{ __('ui.username') }}</label>
                            <input class="field" name="username" value="{{ old('username') }}" autocomplete="username" required>
                        </div>
                        <div>
                            <label class="field-label">{{ __('ui.email_optional') }}</label>
                            <input class="field" type="email" name="email" value="{{ old('email') }}" autocomplete="email">
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="field-label">{{ __('ui.password') }}</label>
                            <input class="field" type="password" name="password" autocomplete="new-password" minlength="8" required>
                        </div>
                        <div>
                            <label class="field-label">{{ __('ui.confirm_password') }}</label>
                            <input class="field" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
                        </div>
                    </div>

                    <div>
                        <label class="field-label">{{ __('ui.preferred_language') }}</label>
                        <select class="field" name="preferred_locale" required>
                            @foreach(config('pos.locales') as $code => $locale)
                                <option value="{{ $code }}" @selected(old('preferred_locale', app()->getLocale()) === $code)>{{ $locale['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">
                        {{ __('ui.setup_security_notice') }}
                    </div>

                    <button class="btn-primary w-full py-3" type="submit">{{ __('ui.create_owner_continue') }}</button>
                </form>

                <p class="mt-5 text-center text-xs leading-5 text-slate-400">{{ __('ui.setup_cli_fallback') }}</p>
            </section>
        </div>
    </div>
</div>
@endsection
