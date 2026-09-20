@extends('layouts.app')

@section('title', __('ui.catalog_setup'))
@section('page-title', __('ui.catalog_setup'))

@section('content')
<div class="space-y-5">
    <div>
        <h2 class="text-2xl font-black">{{ __('ui.catalog_setup') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('ui.catalog_setup_help') }}</p>
    </div>

    @section('page-errors', '1')

    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
            <ul class="list-disc space-y-1 ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-3">
        <section class="panel p-5">
            <h3 class="text-lg font-black">{{ __('ui.categories') }}</h3>
            <form method="POST" action="{{ route('inventory.catalog.categories.store') }}" class="mt-4 space-y-3">
                @csrf
                <input class="field" name="name_en" placeholder="{{ __('ui.name_english') }}" required>
                <input class="field" name="name_fa" placeholder="{{ __('ui.name_dari') }}" dir="rtl">
                <input class="field" name="name_ps" placeholder="{{ __('ui.name_pashto') }}" dir="rtl">
                <select class="field" name="parent_id"><option value="">{{ __('ui.no_parent') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->localizedName() }}</option>@endforeach</select>
                <button class="btn-primary w-full" type="submit">{{ __('ui.add_category') }}</button>
            </form>
            <div class="mt-5 max-h-80 space-y-2 overflow-auto">
                @foreach($categories as $category)<div class="rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60"><strong>{{ $category->localizedName() }}</strong>@if($category->parent)<div class="text-xs text-slate-400">{{ __('ui.parent') }}: {{ $category->parent->localizedName() }}</div>@endif</div>@endforeach
            </div>
        </section>

        <section class="panel p-5">
            <h3 class="text-lg font-black">{{ __('ui.brands') }}</h3>
            <form method="POST" action="{{ route('inventory.catalog.brands.store') }}" class="mt-4 space-y-3">
                @csrf
                <input class="field" name="name_en" placeholder="{{ __('ui.name_english') }}" required>
                <input class="field" name="name_fa" placeholder="{{ __('ui.name_dari') }}" dir="rtl">
                <input class="field" name="name_ps" placeholder="{{ __('ui.name_pashto') }}" dir="rtl">
                <button class="btn-primary w-full" type="submit">{{ __('ui.add_brand') }}</button>
            </form>
            <div class="mt-5 max-h-80 space-y-2 overflow-auto">
                @foreach($brands as $brand)<div class="rounded-xl bg-slate-50 px-3 py-2 text-sm font-semibold dark:bg-slate-800/60">{{ $brand->localizedName() }}</div>@endforeach
            </div>
        </section>

        <section class="panel p-5">
            <h3 class="text-lg font-black">{{ __('ui.units') }}</h3>
            <form method="POST" action="{{ route('inventory.catalog.units.store') }}" class="mt-4 space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3"><input class="field" name="code" placeholder="{{ __('ui.unit_code') }}" required><input class="field" name="symbol" placeholder="{{ __('ui.symbol') }}"></div>
                <input class="field" name="name_en" placeholder="{{ __('ui.name_english') }}" required>
                <input class="field" name="name_fa" placeholder="{{ __('ui.name_dari') }}" dir="rtl">
                <input class="field" name="name_ps" placeholder="{{ __('ui.name_pashto') }}" dir="rtl">
                <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.decimal_places') }}</label><select class="field" name="decimal_places">@for($i=0;$i<=6;$i++)<option value="{{ $i }}">{{ $i }}</option>@endfor</select></div>
                <button class="btn-primary w-full" type="submit">{{ __('ui.add_unit') }}</button>
            </form>
            <div class="mt-5 max-h-80 space-y-2 overflow-auto">
                @foreach($units as $unit)<div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60"><span class="font-semibold">{{ $unit->localizedName() }}</span><span class="font-mono text-xs text-slate-400">{{ $unit->code }} · {{ $unit->decimal_places }}dp</span></div>@endforeach
            </div>
        </section>
    </div>
</div>
@endsection
