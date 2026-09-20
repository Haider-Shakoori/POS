@extends('layouts.app')

@section('title', __('ui.add_product'))
@section('page-title', __('ui.add_product'))

@section('content')
<form method="POST" action="{{ route('inventory.products.store') }}" class="space-y-5" x-data="{
    units: @js(old('units', [])),
    barcodes: @js(old('barcodes', [])),
    addUnit() { this.units.push({ unit_id: '', conversion_factor: '', can_purchase: true, can_sell: true, selling_price: '', minimum_selling_price: '', wholesale_price: '' }) },
    addBarcode() { this.barcodes.push({ barcode: '', unit_id: '', is_primary: false }) }
}">
    @csrf

    @section('page-errors', '1')

    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
            <div class="font-bold">{{ __('ui.fix_validation_errors') }}</div>
            <ul class="mt-2 list-disc space-y-1 ps-5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-5">
            <section class="panel p-5 sm:p-6">
                <div class="mb-5">
                    <h3 class="text-lg font-black">{{ __('ui.product_details') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('ui.product_details_help') }}</p>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-semibold">{{ __('ui.sku') }}</label>
                        <input class="field" name="sku" value="{{ old('sku') }}" required>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold">{{ __('ui.base_unit') }}</label>
                        <select class="field" name="base_unit_id" required>
                            <option value="">{{ __('ui.select_unit') }}</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" @selected((string) old('base_unit_id') === (string) $unit->id)>{{ $unit->localizedName() }} ({{ $unit->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold">{{ __('ui.name_english') }}</label>
                        <input class="field" name="name_en" value="{{ old('name_en') }}" required>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold">{{ __('ui.name_dari') }}</label>
                        <input class="field" name="name_fa" value="{{ old('name_fa') }}" dir="rtl">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold">{{ __('ui.name_pashto') }}</label>
                        <input class="field" name="name_ps" value="{{ old('name_ps') }}" dir="rtl">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold">{{ __('ui.shelf_location') }}</label>
                        <input class="field" name="shelf_location" value="{{ old('shelf_location') }}">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold">{{ __('ui.category') }}</label>
                        <select class="field" name="category_id">
                            <option value="">{{ __('ui.none') }}</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->localizedName() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold">{{ __('ui.brand') }}</label>
                        <select class="field" name="brand_id">
                            <option value="">{{ __('ui.none') }}</option>
                            @foreach($brands as $brand)
                                <option value="{{ $brand->id }}" @selected((string) old('brand_id') === (string) $brand->id)>{{ $brand->localizedName() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>

            <section class="panel p-5 sm:p-6">
                <h3 class="text-lg font-black">{{ __('ui.pricing_stock') }}</h3>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.purchase_cost') }}</label><input class="field" name="purchase_cost" value="{{ old('purchase_cost', '0.00') }}" inputmode="decimal"></div>
                    <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.sale_price') }}</label><input class="field" name="selling_price" value="{{ old('selling_price') }}" inputmode="decimal" required></div>
                    <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.minimum_sale_price') }}</label><input class="field" name="minimum_selling_price" value="{{ old('minimum_selling_price') }}" inputmode="decimal"></div>
                    <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.wholesale_price') }}</label><input class="field" name="wholesale_price" value="{{ old('wholesale_price') }}" inputmode="decimal"></div>
                    <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.minimum_stock') }}</label><input class="field" name="minimum_stock" value="{{ old('minimum_stock', '0') }}" inputmode="decimal"></div>
                    <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.reorder_quantity') }}</label><input class="field" name="reorder_quantity" value="{{ old('reorder_quantity', '0') }}" inputmode="decimal"></div>
                </div>
                <div class="mt-5 flex flex-wrap gap-5">
                    <label class="flex items-center gap-2 text-sm font-semibold"><input class="size-4" type="checkbox" name="track_stock" value="1" @checked(old('track_stock', '1'))>{{ __('ui.track_stock') }}</label>
                    <label class="flex items-center gap-2 text-sm font-semibold"><input class="size-4" type="checkbox" name="track_expiry" value="1" @checked(old('track_expiry'))>{{ __('ui.track_expiry') }}</label>
                </div>
            </section>

            <section class="panel p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <div><h3 class="text-lg font-black">{{ __('ui.alternative_units') }}</h3><p class="mt-1 text-sm text-slate-500">{{ __('ui.conversion_help') }}</p></div>
                    <button class="btn-secondary" type="button" @click="addUnit()">{{ __('ui.add_unit') }}</button>
                </div>
                <div class="mt-5 space-y-3">
                    <template x-for="(row, index) in units" :key="index">
                        <div class="grid gap-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-800 lg:grid-cols-[1.2fr_1fr_1fr_auto]">
                            <select class="field" :name="'units['+index+'][unit_id]'" x-model="row.unit_id" required>
                                <option value="">{{ __('ui.select_unit') }}</option>
                                @foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->localizedName() }} ({{ $unit->code }})</option>@endforeach
                            </select>
                            <input class="field" :name="'units['+index+'][conversion_factor]'" x-model="row.conversion_factor" placeholder="{{ __('ui.conversion_factor') }}" inputmode="decimal" required>
                            <input class="field" :name="'units['+index+'][selling_price]'" x-model="row.selling_price" placeholder="{{ __('ui.sale_price') }}" inputmode="decimal">
                            <div class="flex items-center gap-3">
                                <label class="text-xs"><input type="checkbox" value="1" :name="'units['+index+'][can_purchase]'" x-model="row.can_purchase"> {{ __('ui.buy') }}</label>
                                <label class="text-xs"><input type="checkbox" value="1" :name="'units['+index+'][can_sell]'" x-model="row.can_sell"> {{ __('ui.sell') }}</label>
                                <button type="button" class="text-red-600" @click="units.splice(index,1)">×</button>
                            </div>
                        </div>
                    </template>
                    <div x-show="units.length === 0" class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700">{{ __('ui.no_alternative_units') }}</div>
                </div>
            </section>

            <section class="panel p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <div><h3 class="text-lg font-black">{{ __('ui.barcodes') }}</h3><p class="mt-1 text-sm text-slate-500">{{ __('ui.barcode_help') }}</p></div>
                    <button class="btn-secondary" type="button" @click="addBarcode()">{{ __('ui.add_barcode') }}</button>
                </div>
                <div class="mt-5 space-y-3">
                    <template x-for="(row, index) in barcodes" :key="index">
                        <div class="grid gap-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-800 md:grid-cols-[1.3fr_1fr_auto]">
                            <input class="field font-mono" :name="'barcodes['+index+'][barcode]'" x-model="row.barcode" placeholder="{{ __('ui.barcode') }}" required>
                            <select class="field" :name="'barcodes['+index+'][unit_id]'" x-model="row.unit_id" required>
                                <option value="">{{ __('ui.barcode_unit') }}</option>
                                @foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->localizedName() }}</option>@endforeach
                            </select>
                            <div class="flex items-center gap-3">
                                <label class="text-xs"><input type="checkbox" value="1" :name="'barcodes['+index+'][is_primary]'" x-model="row.is_primary"> {{ __('ui.primary') }}</label>
                                <button type="button" class="text-red-600" @click="barcodes.splice(index,1)">×</button>
                            </div>
                        </div>
                    </template>
                </div>
            </section>
        </div>

        <aside class="space-y-5">
            <div class="panel p-5">
                <h3 class="font-black">{{ __('ui.inventory_rules') }}</h3>
                <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-500">
                    <li>• {{ __('ui.base_unit_rule') }}</li>
                    <li>• {{ __('ui.stock_ledger_rule') }}</li>
                    <li>• {{ __('ui.expiry_batch_rule') }}</li>
                </ul>
            </div>
            <div class="panel p-5">
                <button class="btn-primary w-full" type="submit">{{ __('ui.create_product') }}</button>
                <a href="{{ route('inventory.products.index') }}" class="btn-secondary mt-3 w-full">{{ __('ui.cancel') }}</a>
            </div>
        </aside>
    </div>
</form>
@endsection
