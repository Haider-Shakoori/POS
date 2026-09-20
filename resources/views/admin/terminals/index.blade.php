@extends('layouts.app')

@section('title', __('ui.terminals'))
@section('page-title', __('ui.terminals'))

@section('content')
<div class="space-y-6">
    <div>
        <p class="eyebrow">{{ __('ui.administration') }}</p>
        <h2 class="page-heading">{{ __('ui.terminals') }}</h2>
        <p class="page-subtitle">{{ __('ui.terminals_help') }}</p>
    </div>

    @section('page-errors', '1')

    @if($errors->any())<div class="alert-error">{{ $errors->first() }}</div>@endif

    <div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
        <section class="panel p-5">
            <h3 class="section-heading">{{ __('ui.add_terminal') }}</h3>
            <form method="POST" action="{{ route('settings.terminals.store') }}" class="mt-5 space-y-4">
                @csrf
                <div><label class="field-label">{{ __('ui.code') }}</label><input class="field" name="code" value="{{ old('code') }}" required></div>
                <div><label class="field-label">{{ __('ui.name') }}</label><input class="field" name="name" value="{{ old('name') }}" required></div>
                <input type="hidden" name="is_active" value="0">
                <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" checked> {{ __('ui.active') }}</label>
                <button class="btn-primary w-full" type="submit">{{ __('ui.add_terminal') }}</button>
            </form>
        </section>

        <section class="panel overflow-hidden">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                <h3 class="section-heading">{{ __('ui.registered_terminals') }}</h3>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($terminals as $terminal)
                    <form method="POST" action="{{ route('settings.terminals.update', $terminal) }}" class="grid gap-3 p-5 md:grid-cols-[10rem_minmax(0,1fr)_7rem_auto] md:items-end">
                        @csrf
                        @method('PUT')
                        <div><label class="field-label">{{ __('ui.code') }}</label><input class="field" name="code" value="{{ $terminal->code }}" required></div>
                        <div>
                            <label class="field-label">{{ __('ui.name') }}</label>
                            <input class="field" name="name" value="{{ $terminal->name }}" required>
                            <div class="mt-1 text-xs text-slate-400">{{ trans_choice('ui.shift_count', $terminal->shifts_count, ['count' => $terminal->shifts_count]) }}</div>
                        </div>
                        <div>
                            <input type="hidden" name="is_active" value="0">
                            <label class="flex h-11 items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" @checked($terminal->is_active)> {{ __('ui.active') }}</label>
                        </div>
                        <button class="btn-secondary" type="submit">{{ __('ui.save') }}</button>
                    </form>
                @endforeach
            </div>
            @if($terminals->hasPages())<div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $terminals->links() }}</div>@endif
        </section>
    </div>
</div>
@endsection
