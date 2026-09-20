@extends('layouts.app')

@section('title', __('ui.new_purchase_order'))
@section('page-title', __('ui.new_purchase_order'))

@section('content')
@php
    $unitOptions = $productUnits->map(fn ($pu) => [
        'id' => $pu->id,
        'label' => $pu->product->localizedName().' · '.$pu->unit->localizedName().' (×'.\App\Support\Decimal::display($pu->conversion_factor).')',
        'cost' => $pu->product->purchase_cost,
    ])->values();
@endphp

<form method="POST" action="{{ route('purchasing.orders.store') }}"
      x-data="{
        items: @js(old('items', [['product_unit_id' => '', 'quantity' => '1', 'unit_cost' => '', 'line_discount_amount' => '0']])),
        options: @js($unitOptions),
        addItem() { this.items.push({product_unit_id:'', quantity:'1', unit_cost:'', line_discount_amount:'0'}) },
        setCost(row) { const option = this.options.find(x => String(x.id) === String(row.product_unit_id)); if (option && !row.unit_cost) row.unit_cost = option.cost; }
      }"
      class="space-y-5">
    @csrf

    @section('page-errors', '1')

    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
            <ul class="list-disc space-y-1 ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_23rem]">
        <div class="space-y-5">
            <section class="panel p-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.supplier') }}</label><select class="field" name="supplier_id" required><option value="">{{ __('ui.select_supplier') }}</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string)old('supplier_id')===(string)$supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
                    <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.order_date') }}</label><input class="field" type="date" name="order_date" value="{{ old('order_date', now()->format('Y-m-d')) }}" required></div>
                    <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.expected_date') }}</label><input class="field" type="date" name="expected_date" value="{{ old('expected_date') }}"></div>
                    <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.supplier_reference') }}</label><input class="field" name="supplier_reference" value="{{ old('supplier_reference') }}"></div>
                </div>
            </section>

            <section class="panel p-5">
                <div class="flex items-center justify-between gap-3"><div><h3 class="text-lg font-black">{{ __('ui.order_items') }}</h3><p class="mt-1 text-sm text-slate-500">{{ __('ui.order_items_help') }}</p></div><button class="btn-secondary" type="button" @click="addItem()">{{ __('ui.add_item') }}</button></div>
                <div class="mt-5 space-y-3">
                    <template x-for="(row,index) in items" :key="index">
                        <div class="grid gap-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-800 lg:grid-cols-[minmax(14rem,1.7fr)_8rem_9rem_8rem_auto]">
                            <select class="field" :name="'items['+index+'][product_unit_id]'" x-model="row.product_unit_id" @change="setCost(row)" required>
                                <option value="">{{ __('ui.select_product_unit') }}</option>
                                @foreach($unitOptions as $option)<option value="{{ $option['id'] }}">{{ $option['label'] }}</option>@endforeach
                            </select>
                            <input class="field" :name="'items['+index+'][quantity]'" x-model="row.quantity" inputmode="decimal" placeholder="{{ __('ui.quantity') }}" required>
                            <input class="field" :name="'items['+index+'][unit_cost]'" x-model="row.unit_cost" inputmode="decimal" placeholder="{{ __('ui.unit_cost') }}" required>
                            <input class="field" :name="'items['+index+'][line_discount_amount]'" x-model="row.line_discount_amount" inputmode="decimal" placeholder="{{ __('ui.discount') }}">
                            <button type="button" class="text-red-600" @click="items.splice(index,1)" :disabled="items.length===1">×</button>
                        </div>
                    </template>
                </div>
            </section>
        </div>

        <aside class="space-y-5">
            <section class="panel p-5">
                <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.order_discount') }}</label><input class="field" name="order_discount_amount" value="{{ old('order_discount_amount','0.00') }}" inputmode="decimal"></div>
                <div class="mt-4"><label class="mb-2 block text-sm font-semibold">{{ __('ui.notes') }}</label><textarea class="field" name="notes" rows="4">{{ old('notes') }}</textarea></div>
            </section>
            <section class="panel p-5">
                <div class="rounded-2xl bg-brand-50 p-4 text-sm leading-6 text-brand-800 dark:bg-brand-950/40 dark:text-brand-200">{{ __('ui.po_no_stock_notice') }}</div>
                <button class="btn-primary mt-4 w-full" type="submit">{{ __('ui.create_purchase_order') }}</button>
            </section>
        </aside>
    </div>
</form>
@endsection
