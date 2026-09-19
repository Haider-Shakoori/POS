@extends('layouts.app')

@section('title', __('ui.shop_settings'))
@section('page-title', __('ui.shop_settings'))

@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <div>
        <h2 class="text-2xl font-black">{{ __('ui.shop_settings') }}</h2>
        <p class="mt-1 max-w-3xl text-sm text-slate-500">{{ __('ui.shop_settings_help') }}</p>
    </div>

    <form method="POST" action="{{ route('settings.shop.update') }}" class="panel p-5">
        @csrf
        @method('PUT')

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-semibold">{{ __('ui.shop_name') }}</label>
                <input class="field" name="shop_name" value="{{ old('shop_name', $settings->shop_name) }}" required>
                @error('shop_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold">{{ __('ui.phone') }}</label>
                <input class="field" name="phone" value="{{ old('phone', $settings->phone) }}">
                @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-semibold">{{ __('ui.address') }}</label>
                <input class="field" name="address" value="{{ old('address', $settings->address) }}">
                @error('address')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold">{{ __('ui.default_language') }}</label>
                <select class="field" name="default_locale">
                    @foreach(['en' => 'English', 'fa' => 'دری', 'ps' => 'پښتو'] as $code => $label)
                        <option value="{{ $code }}" @selected(old('default_locale', $settings->default_locale) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold">{{ __('ui.receipt_language') }}</label>
                <select class="field" name="receipt_locale">
                    @foreach(['en' => 'English', 'fa' => 'دری', 'ps' => 'پښتو'] as $code => $label)
                        <option value="{{ $code }}" @selected(old('receipt_locale', $settings->receipt_locale) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold">{{ __('ui.receipt_size') }}</label>
                <select class="field" name="receipt_size">
                    <option value="57mm" @selected(old('receipt_size', $settings->receipt_size) === '57mm')>57mm</option>
                    <option value="80mm" @selected(old('receipt_size', $settings->receipt_size) === '80mm')>80mm</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold">{{ __('ui.cash_variance_tolerance') }}</label>
                <input class="field" type="number" min="0" step="0.01" name="cash_variance_tolerance" value="{{ old('cash_variance_tolerance', $settings->cash_variance_tolerance) }}" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold">{{ __('ui.discount_approval_threshold') }}</label>
                <input class="field" type="number" min="0" step="0.01" name="discount_approval_threshold" value="{{ old('discount_approval_threshold', $settings->discount_approval_threshold) }}">
                <p class="mt-1 text-xs text-slate-500">{{ __('ui.discount_threshold_help') }}</p>
            </div>
            <div class="flex items-center">
                <label class="flex items-center gap-3">
                    <input type="hidden" name="negative_stock_enabled" value="0">
                    <input type="checkbox" name="negative_stock_enabled" value="1" @checked(old('negative_stock_enabled', $settings->negative_stock_enabled))>
                    <span class="text-sm font-semibold">{{ __('ui.allow_negative_stock') }}</span>
                </label>
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <button class="btn-primary" type="submit">{{ __('ui.save_settings') }}</button>
        </div>
    </form>
</div>
@endsection
