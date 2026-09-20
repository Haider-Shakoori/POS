@extends('layouts.app')

@section('title', __('ui.audit_log'))
@section('page-title', __('ui.audit_log'))

@section('content')
<div class="space-y-6">
    <div>
        <p class="eyebrow">{{ __('ui.administration') }}</p>
        <h2 class="page-heading">{{ __('ui.audit_log') }}</h2>
        <p class="page-subtitle">{{ __('ui.audit_log_help') }}</p>
    </div>

    <section class="panel overflow-hidden">
        <div class="border-b border-slate-200 p-5 dark:border-slate-800">
            <form method="GET" class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <input class="field xl:col-span-2" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('ui.search_audit') }}">
                <select class="field" name="event">
                    <option value="">{{ __('ui.all_events') }}</option>
                    @foreach($events as $event)
                        <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ $event }}</option>
                    @endforeach
                </select>
                <select class="field" name="actor_user_id">
                    <option value="">{{ __('ui.all_users') }}</option>
                    @foreach($actors as $actor)
                        <option value="{{ $actor->id }}" @selected((string)($filters['actor_user_id'] ?? '') === (string)$actor->id)>{{ $actor->name }} · {{ $actor->username }}</option>
                    @endforeach
                </select>
                <input class="field" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
                <input class="field" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
                <div class="md:col-span-2 xl:col-span-6 flex justify-end gap-2">
                    <a class="btn-secondary" href="{{ route('admin.audit.index') }}">{{ __('ui.reset') }}</a>
                    <button class="btn-primary" type="submit">{{ __('ui.apply_filters') }}</button>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('ui.date') }}</th>
                        <th>{{ __('ui.event') }}</th>
                        <th>{{ __('ui.actor') }}</th>
                        <th>{{ __('ui.target') }}</th>
                        <th>{{ __('ui.ip_address') }}</th>
                        <th class="text-end">{{ __('ui.details') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td><span class="badge badge-neutral">{{ $log->event }}</span></td>
                            <td>{{ $log->actor?->name ?? __('ui.system') }}<div class="text-xs text-slate-500">{{ $log->actor?->username }}</div></td>
                            <td>
                                @if($log->auditable_type)
                                    <div class="font-medium">{{ class_basename($log->auditable_type) }}</div>
                                    <div class="text-xs text-slate-500">#{{ $log->auditable_id }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $log->ip_address ?: '—' }}</td>
                            <td class="text-end">
                                @if($log->old_values || $log->new_values)
                                    <details class="inline-block text-start">
                                        <summary class="cursor-pointer text-sm font-semibold text-brand-700 dark:text-brand-300">{{ __('ui.view_changes') }}</summary>
                                        <div class="mt-2 min-w-80 max-w-xl rounded-xl bg-slate-950 p-3 text-xs text-slate-100 shadow-xl">
                                            @if($log->old_values)
                                                <div class="mb-2 font-bold text-slate-400">{{ __('ui.before') }}</div>
                                                <pre class="whitespace-pre-wrap break-words">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            @endif
                                            @if($log->new_values)
                                                <div class="mb-2 mt-3 font-bold text-slate-400">{{ __('ui.after') }}</div>
                                                <pre class="whitespace-pre-wrap break-words">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            @endif
                                        </div>
                                    </details>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">{{ __('ui.no_audit_entries') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())<div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $logs->links() }}</div>@endif
    </section>
</div>
@endsection
