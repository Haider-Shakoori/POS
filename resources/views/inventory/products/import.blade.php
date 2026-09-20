@extends('layouts.app')

@section('title', __('ui.import_products'))
@section('page-title', __('ui.import_products'))

@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <h2 class="text-2xl font-black">{{ __('ui.import_products') }}</h2>
            <p class="mt-1 max-w-3xl text-sm text-slate-500">{{ __('ui.import_products_help') }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('inventory.products.import-template') }}" class="btn-secondary">{{ __('ui.download_template') }}</a>
            <a href="{{ route('inventory.products.index') }}" class="btn-secondary">{{ __('ui.back_to_products') }}</a>
        </div>
    </div>

    @section('page-errors', '1')

    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">
            <div class="font-bold">{{ __('ui.import_failed') }}</div>
            <ul class="mt-2 list-disc space-y-1 ps-5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <section class="panel p-5">
        <form method="POST" action="{{ route('inventory.products.import') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-semibold">{{ __('ui.csv_file') }}</label>
                <input class="field" type="file" name="file" accept=".csv,text/csv,text/plain" required>
                <p class="mt-2 text-xs text-slate-500">{{ __('ui.import_products_safety') }}</p>
            </div>
            <button class="btn-primary" type="submit">{{ __('ui.import_products') }}</button>
        </form>
    </section>

    <section class="panel p-5">
        <h3 class="font-black">{{ __('ui.csv_columns') }}</h3>
        <p class="mt-1 text-sm text-slate-500">{{ __('ui.csv_columns_help') }}</p>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach($headers as $header)
                <code class="rounded-lg bg-slate-100 px-2 py-1 text-xs dark:bg-slate-800">{{ $header }}</code>
            @endforeach
        </div>
    </section>
</div>
@endsection
