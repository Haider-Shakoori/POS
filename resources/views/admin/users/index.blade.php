@extends('layouts.app')

@section('title', __('ui.users_access'))
@section('page-title', __('ui.users_access'))

@section('content')
<div class="space-y-6">
    <div>
        <p class="eyebrow">{{ __('ui.administration') }}</p>
        <h2 class="page-heading">{{ __('ui.users_access') }}</h2>
        <p class="page-subtitle">{{ __('ui.users_access_help') }}</p>
    </div>

    @if($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <section class="panel p-5 sm:p-6">
        <h3 class="section-heading">{{ __('ui.create_user') }}</h3>
        <form method="POST" action="{{ route('admin.users.store') }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @csrf
            <div>
                <label class="field-label">{{ __('ui.name') }}</label>
                <input class="field" name="name" value="{{ old('name') }}" required>
            </div>
            <div>
                <label class="field-label">{{ __('ui.username') }}</label>
                <input class="field" name="username" value="{{ old('username') }}" required>
            </div>
            <div>
                <label class="field-label">{{ __('ui.email') }}</label>
                <input class="field" type="email" name="email" value="{{ old('email') }}">
            </div>
            <div>
                <label class="field-label">{{ __('ui.password') }}</label>
                <input class="field" type="password" name="password" required minlength="8">
            </div>
            <div>
                <label class="field-label">{{ __('ui.language') }}</label>
                <select class="field" name="preferred_locale">
                    @foreach(config('pos.locales') as $code => $locale)
                        <option value="{{ $code }}" @selected(old('preferred_locale', 'en') === $code)>{{ $locale['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="field-label">{{ __('ui.roles') }}</label>
                <div class="flex flex-wrap gap-2 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                    @foreach($roles as $role)
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked(in_array((string)$role->id, array_map('strval', old('role_ids', [])), true))>
                            <span>{{ $role->label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="flex items-end">
                <input type="hidden" name="is_active" value="0">
                <label class="inline-flex items-center gap-2 pb-3 text-sm font-semibold">
                    <input type="checkbox" name="is_active" value="1" checked>
                    {{ __('ui.active') }}
                </label>
            </div>
            <div class="md:col-span-2 xl:col-span-4 flex justify-end">
                <button class="btn-primary" type="submit">{{ __('ui.create_user') }}</button>
            </div>
        </form>
    </section>

    <section class="panel overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div>
                <h3 class="section-heading">{{ __('ui.team_accounts') }}</h3>
                <p class="text-sm text-slate-500">{{ __('ui.team_accounts_help') }}</p>
            </div>
            <form method="GET" class="flex gap-2">
                <input class="field min-w-64" name="q" value="{{ request('q') }}" placeholder="{{ __('ui.search_users') }}">
                <button class="btn-secondary" type="submit">{{ __('ui.search') }}</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('ui.user') }}</th>
                        <th>{{ __('ui.roles') }}</th>
                        <th>{{ __('ui.language') }}</th>
                        <th>{{ __('ui.status') }}</th>
                        <th class="text-end">{{ __('ui.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td>
                                <div class="font-semibold">{{ $user->name }}</div>
                                <div class="text-xs text-slate-500">{{ '@'.$user->username }}{{ $user->email ? ' · '.$user->email : '' }}</div>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($user->roles as $role)
                                        <span class="badge badge-neutral">{{ $role->label }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td>{{ config('pos.locales.'.$user->preferred_locale.'.label', strtoupper($user->preferred_locale)) }}</td>
                            <td><span class="badge {{ $user->is_active ? 'badge-success' : 'badge-neutral' }}">{{ $user->is_active ? __('ui.active') : __('ui.inactive') }}</span></td>
                            <td class="text-end">
                                @if(!$user->hasRole('owner') || $isOwner)
                                    <details class="inline-block text-start">
                                        <summary class="btn-secondary cursor-pointer list-none">{{ __('ui.edit') }}</summary>
                                        <div class="fixed inset-0 z-40 bg-slate-950/40" onclick="this.parentElement.removeAttribute('open')"></div>
                                        <div class="fixed inset-y-0 end-0 z-50 w-full max-w-xl overflow-y-auto bg-white p-6 shadow-2xl dark:bg-slate-900">
                                            <div class="flex items-center justify-between">
                                                <h3 class="text-xl font-black">{{ __('ui.edit_user') }}</h3>
                                                <button type="button" class="btn-secondary px-3" onclick="this.closest('details').removeAttribute('open')">×</button>
                                            </div>
                                            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-6 space-y-4">
                                                @csrf
                                                @method('PUT')
                                                <div><label class="field-label">{{ __('ui.name') }}</label><input class="field" name="name" value="{{ $user->name }}" required></div>
                                                <div><label class="field-label">{{ __('ui.username') }}</label><input class="field" name="username" value="{{ $user->username }}" required></div>
                                                <div><label class="field-label">{{ __('ui.email') }}</label><input class="field" type="email" name="email" value="{{ $user->email }}"></div>
                                                <div><label class="field-label">{{ __('ui.new_password_optional') }}</label><input class="field" type="password" name="password" minlength="8"></div>
                                                <div>
                                                    <label class="field-label">{{ __('ui.language') }}</label>
                                                    <select class="field" name="preferred_locale">
                                                        @foreach(config('pos.locales') as $code => $locale)
                                                            <option value="{{ $code }}" @selected($user->preferred_locale === $code)>{{ $locale['label'] }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="field-label">{{ __('ui.roles') }}</label>
                                                    <div class="space-y-2 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                                                        @foreach($roles as $role)
                                                            <label class="flex items-center gap-2 text-sm">
                                                                <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked($user->roles->contains('id', $role->id))>
                                                                <span>{{ $role->label }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                <input type="hidden" name="is_active" value="0">
                                                <label class="flex items-center gap-2 text-sm font-semibold">
                                                    <input type="checkbox" name="is_active" value="1" @checked($user->is_active)>
                                                    {{ __('ui.active_account') }}
                                                </label>
                                                <button class="btn-primary w-full" type="submit">{{ __('ui.save_changes') }}</button>
                                            </form>
                                        </div>
                                    </details>
                                @else
                                    <span class="text-xs text-slate-400">{{ __('ui.owner_only') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($users->hasPages())<div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $users->links() }}</div>@endif
    </section>
</div>
@endsection
