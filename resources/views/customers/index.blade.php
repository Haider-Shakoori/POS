@extends('layouts.app')

@section('title', __('ui.customers'))
@section('page-title', __('ui.customers'))

@section('content')
<div class="space-y-5">
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
        <div>
            <h2 class="text-2xl font-black">{{ __('ui.customers') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('ui.customers_help') }}</p>
        </div>

        <form method="GET" class="flex w-full gap-2 lg:max-w-md">
            <input class="field" name="q" value="{{ $search }}" placeholder="{{ __('ui.search_customers') }}">
            <button class="btn-secondary" type="submit">{{ __('ui.search') }}</button>
        </form>
    </div>

    @if(auth()->user()->hasPermission('customers.manage'))
        <section class="panel p-5">
            <div class="mb-4">
                <h3 class="font-black">{{ __('ui.add_customer') }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ __('ui.customer_credit_help') }}</p>
            </div>

            <form method="POST" action="{{ route('customers.store') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.customer_name') }}</label>
                    <input class="field" name="name" value="{{ old('name') }}" required>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.phone') }}</label>
                    <input class="field" name="phone" value="{{ old('phone') }}">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.credit_limit') }}</label>
                    <input class="field" name="credit_limit" value="{{ old('credit_limit','0.00') }}" inputmode="decimal">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.opening_balance') }}</label>
                    <input class="field" name="opening_balance" value="{{ old('opening_balance','0.00') }}" inputmode="decimal">
                </div>
                <div class="flex items-end">
                    <button class="btn-primary w-full" type="submit">{{ __('ui.create_customer') }}</button>
                </div>
            </form>

            @error('customer')
                <p class="mt-3 text-sm font-semibold text-red-600">{{ $message }}</p>
            @enderror
        </section>
    @endif

    <section class="panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-950/40">
                    <tr>
                        <th class="px-5 py-3 text-start">{{ __('ui.customer') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.phone') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.credit_limit') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('ui.current_balance') }}</th>
                        <th class="px-5 py-3 text-start">{{ __('ui.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($customers as $customer)
                        <tr>
                            <td class="px-5 py-4">
                                <a class="font-bold text-brand-700 hover:underline dark:text-brand-300" href="{{ route('customers.show',$customer) }}">{{ $customer->name }}</a>
                            </td>
                            <td class="px-5 py-4">{{ $customer->phone ?: '—' }}</td>
                            <td class="px-5 py-4 text-end">{{ \App\Support\Money::format($customer->credit_limit) }}</td>
                            <td class="px-5 py-4 text-end font-bold">{{ \App\Support\Money::format($customer->current_balance) }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full px-2 py-1 text-xs font-bold {{ $customer->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">
                                    {{ $customer->is_active ? __('ui.active') : __('ui.inactive') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">{{ __('ui.no_customers') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $customers->links() }}</div>
    </section>
</div>
@endsection
