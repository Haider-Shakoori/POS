@extends('layouts.app')

@section('title', __('ui.operating_entries'))
@section('page-title', __('ui.operating_entries'))

@section('content')
@php
    $money = fn ($value) => \App\Support\Money::format((string) $value);
@endphp

<div class="space-y-6">
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
        <div>
            <p class="eyebrow">{{ __('ui.finance') }}</p>
            <h2 class="page-heading">{{ __('ui.operating_entries') }}</h2>
            <p class="page-subtitle">{{ __('ui.operating_entries_help') }}</p>
        </div>
        @if(auth()->user()->hasPermission('cash.view'))
            <a class="btn-secondary" href="{{ route('cash.index') }}">{{ __('ui.open_cash_drawer') }}</a>
        @endif
    </div>

    @error('operating_entry')
        <div class="alert-error">{{ $message }}</div>
    @enderror

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="metric-card">
            <div class="metric-label">{{ __('ui.filtered_expenses') }}</div>
            <div class="metric-value text-rose-600 dark:text-rose-300">{{ $money($expenseTotal) }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">{{ __('ui.filtered_other_income') }}</div>
            <div class="metric-value text-emerald-600 dark:text-emerald-300">{{ $money($incomeTotal) }}</div>
        </div>
    </div>

    @if(auth()->user()->hasPermission('expenses.create'))
        <section class="panel p-5 sm:p-6"
            x-data="{
                type: 'expense',
                categories: @js($categories->map(fn($category) => ['id' => $category->id, 'name' => $category->name, 'type' => $category->entry_type])->values()),
                options() { return this.categories.filter(item => item.type === this.type) }
            }">
            <div class="flex flex-col gap-1">
                <h3 class="section-heading">{{ __('ui.record_operating_entry') }}</h3>
                <p class="text-sm text-slate-500">{{ __('ui.operating_entry_form_help') }}</p>
            </div>

            <form method="POST" action="{{ route('expenses.store') }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <input type="hidden" name="return_to" value="expenses">

                <div>
                    <label class="field-label">{{ __('ui.type') }}</label>
                    <select class="field" name="entry_type" x-model="type" required>
                        <option value="expense">{{ __('ui.operating_expense') }}</option>
                        <option value="income">{{ __('ui.other_income') }}</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">{{ __('ui.category') }}</label>
                    <select class="field" name="expense_category_id" required>
                        <template x-for="category in options()" :key="category.id">
                            <option :value="category.id" x-text="category.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="field-label">{{ __('ui.payment_method') }}</label>
                    <select class="field" name="payment_method_id" required>
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method->id }}">{{ $method->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">{{ __('ui.amount') }}</label>
                    <input class="field" name="amount" inputmode="decimal" required>
                </div>
                <div>
                    <label class="field-label">{{ __('ui.reference') }}</label>
                    <input class="field" name="reference" maxlength="120">
                </div>
                <div>
                    <label class="field-label">{{ __('ui.date') }}</label>
                    <input class="field" type="datetime-local" name="occurred_at">
                </div>
                <div class="md:col-span-2">
                    <label class="field-label">{{ __('ui.description') }}</label>
                    <input class="field" name="description" maxlength="1000">
                </div>
                <div class="md:col-span-2 xl:col-span-4 flex justify-end">
                    <button class="btn-primary" type="submit">{{ __('ui.save_entry') }}</button>
                </div>
            </form>
        </section>
    @endif

    <section class="panel overflow-hidden">
        <div class="border-b border-slate-200 p-5 dark:border-slate-800">
            <form method="GET" class="grid gap-3 md:grid-cols-2 xl:grid-cols-7">
                <input class="field xl:col-span-2" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('ui.search_reference_description') }}">
                <select class="field" name="entry_type">
                    <option value="">{{ __('ui.all_types') }}</option>
                    <option value="expense" @selected(($filters['entry_type'] ?? '') === 'expense')>{{ __('ui.operating_expense') }}</option>
                    <option value="income" @selected(($filters['entry_type'] ?? '') === 'income')>{{ __('ui.other_income') }}</option>
                </select>
                <select class="field" name="category_id">
                    <option value="">{{ __('ui.all_categories') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string)($filters['category_id'] ?? '') === (string)$category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <select class="field" name="payment_method_id">
                    <option value="">{{ __('ui.all_payment_methods') }}</option>
                    @foreach($paymentMethods as $method)
                        <option value="{{ $method->id }}" @selected((string)($filters['payment_method_id'] ?? '') === (string)$method->id)>{{ $method->name }}</option>
                    @endforeach
                </select>
                <input class="field" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
                <input class="field" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
                <div class="md:col-span-2 xl:col-span-7 flex justify-end gap-2">
                    <a class="btn-secondary" href="{{ route('expenses.index') }}">{{ __('ui.reset') }}</a>
                    <button class="btn-primary" type="submit">{{ __('ui.apply_filters') }}</button>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('ui.document_number') }}</th>
                        <th>{{ __('ui.date') }}</th>
                        <th>{{ __('ui.type') }}</th>
                        <th>{{ __('ui.category') }}</th>
                        <th>{{ __('ui.payment_method') }}</th>
                        <th>{{ __('ui.reference') }}</th>
                        <th>{{ __('ui.recorded_by') }}</th>
                        <th class="text-end">{{ __('ui.amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        <tr>
                            <td class="font-semibold">{{ $entry->number }}</td>
                            <td>{{ $entry->occurred_at->format('Y-m-d H:i') }}</td>
                            <td>
                                <span class="badge {{ $entry->entry_type === 'expense' ? 'badge-danger' : 'badge-success' }}">
                                    {{ $entry->entry_type === 'expense' ? __('ui.operating_expense') : __('ui.other_income') }}
                                </span>
                            </td>
                            <td>{{ $entry->category?->name }}</td>
                            <td>{{ $entry->paymentMethod?->name }}</td>
                            <td>{{ $entry->reference ?: '—' }}</td>
                            <td>{{ $entry->recordedBy?->name ?? '—' }}</td>
                            <td class="text-end font-bold">{{ $money($entry->amount) }}</td>
                        </tr>
                        @if($entry->description)
                            <tr class="bg-slate-50/50 dark:bg-slate-950/30">
                                <td colspan="8" class="text-xs text-slate-500">{{ $entry->description }}</td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="8" class="empty-state">{{ __('ui.no_operating_entries') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($entries->hasPages())
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $entries->links() }}</div>
        @endif
    </section>
</div>
@endsection
