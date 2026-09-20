@extends('layouts.app')

@section('title', __('ui.roles_permissions'))
@section('page-title', __('ui.roles_permissions'))

@section('content')
<div class="space-y-6">
    <div>
        <p class="eyebrow">{{ __('ui.administration') }}</p>
        <h2 class="page-heading">{{ __('ui.roles_permissions') }}</h2>
        <p class="page-subtitle">{{ __('ui.roles_permissions_help') }}</p>
    </div>

    @if($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <section class="panel p-5 sm:p-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="section-heading">{{ __('ui.create_role') }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ __('ui.create_role_help') }}</p>
            </div>
            <span class="badge badge-neutral">{{ $permissions->count() }} {{ __('ui.permissions') }}</span>
        </div>

        <form method="POST" action="{{ route('admin.roles.store') }}" class="mt-5 space-y-5">
            @csrf

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="field-label">{{ __('ui.role_name') }}</label>
                    <input class="field" name="name" value="{{ old('name') }}" placeholder="sales_supervisor" required pattern="[a-z][a-z0-9_]*">
                    <p class="mt-1 text-[11px] text-slate-400">{{ __('ui.role_name_help') }}</p>
                </div>
                <div>
                    <label class="field-label">{{ __('ui.role_label') }}</label>
                    <input class="field" name="label" value="{{ old('label') }}" placeholder="{{ __('ui.role_label_example') }}" required>
                </div>
            </div>

            <div>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <label class="field-label mb-0">{{ __('ui.permissions') }}</label>
                    <span class="text-xs text-slate-400">{{ __('ui.permissions_application_defined') }}</span>
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    @foreach($permissionGroups as $group => $groupPermissions)
                        <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                            <div class="mb-3 text-xs font-black uppercase tracking-wider text-slate-400">
                                {{ str($group)->replace('_', ' ')->title() }}
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach($groupPermissions as $permission)
                                    <label class="flex gap-2 rounded-xl p-2 text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                        <input
                                            class="mt-0.5"
                                            type="checkbox"
                                            name="permission_ids[]"
                                            value="{{ $permission->id }}"
                                            @checked(in_array((string) $permission->id, array_map('strval', old('permission_ids', [])), true))
                                        >
                                        <span class="min-w-0">
                                            <span class="block font-semibold">{{ $permission->label }}</span>
                                            <span class="block truncate text-[11px] text-slate-400">{{ $permission->name }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end">
                <button class="btn-primary" type="submit">{{ __('ui.create_role') }}</button>
            </div>
        </form>
    </section>

    <section class="space-y-4">
        @foreach($roles as $role)
            @php
                $isOwnerRole = $role->name === 'owner';
                $isProtectedRole = in_array($role->name, $protectedRoleNames, true);
                $assignedPermissionIds = $role->permissions->pluck('id')->map(fn ($id) => (string) $id)->all();
            @endphp

            <details class="panel overflow-hidden" @if($isOwnerRole) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 sm:p-6">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="truncate text-lg font-black">{{ $role->label }}</h3>
                            @if($isOwnerRole)
                                <span class="badge badge-success">{{ __('ui.full_system_access') }}</span>
                            @elseif($isProtectedRole)
                                <span class="badge badge-neutral">{{ __('ui.system_role') }}</span>
                            @else
                                <span class="badge badge-neutral">{{ __('ui.custom_role') }}</span>
                            @endif
                        </div>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                            <span>{{ $role->name }}</span>
                            <span>{{ trans_choice('ui.user_count', $role->users_count, ['count' => $role->users_count]) }}</span>
                            <span>
                                {{ $isOwnerRole ? __('ui.all_permissions') : trans_choice('ui.permission_count', $role->permissions->count(), ['count' => $role->permissions->count()]) }}
                            </span>
                        </div>
                    </div>
                    <svg class="size-5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                </summary>

                <div class="border-t border-slate-200 p-5 sm:p-6 dark:border-slate-800">
                    @if($isOwnerRole)
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300">
                            {{ __('ui.owner_role_help') }}
                        </div>
                    @else
                        <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-5">
                            @csrf
                            @method('PUT')

                            <div>
                                <label class="field-label">{{ __('ui.role_label') }}</label>
                                <input class="field max-w-xl" name="label" value="{{ $role->label }}" required>
                            </div>

                            <div class="grid gap-4 xl:grid-cols-2">
                                @foreach($permissionGroups as $group => $groupPermissions)
                                    <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                        <div class="mb-3 text-xs font-black uppercase tracking-wider text-slate-400">
                                            {{ str($group)->replace('_', ' ')->title() }}
                                        </div>
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            @foreach($groupPermissions as $permission)
                                                <label class="flex gap-2 rounded-xl p-2 text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                                    <input
                                                        class="mt-0.5"
                                                        type="checkbox"
                                                        name="permission_ids[]"
                                                        value="{{ $permission->id }}"
                                                        @checked(in_array((string) $permission->id, $assignedPermissionIds, true))
                                                    >
                                                    <span class="min-w-0">
                                                        <span class="block font-semibold">{{ $permission->label }}</span>
                                                        <span class="block truncate text-[11px] text-slate-400">{{ $permission->name }}</span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex flex-wrap justify-between gap-3">
                                <div class="text-xs text-slate-400">
                                    {{ __('ui.role_key_locked') }}: <span class="font-mono">{{ $role->name }}</span>
                                </div>
                                <button class="btn-primary" type="submit">{{ __('ui.save_permissions') }}</button>
                            </div>
                        </form>

                        @unless($isProtectedRole)
                            <form
                                method="POST"
                                action="{{ route('admin.roles.destroy', $role) }}"
                                class="mt-5 border-t border-slate-200 pt-5 dark:border-slate-800"
                                onsubmit="return confirm(@js(__('ui.delete_role_confirm')))"
                            >
                                @csrf
                                @method('DELETE')
                                <button class="btn-secondary border-rose-200 text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/30" type="submit">
                                    {{ __('ui.delete_role') }}
                                </button>
                            </form>
                        @endunless
                    @endif
                </div>
            </details>
        @endforeach
    </section>
</div>
@endsection
