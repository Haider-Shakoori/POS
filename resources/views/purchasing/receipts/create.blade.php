@extends('layouts.app')

@section('title', __('ui.post_goods_receipt'))
@section('page-title', __('ui.post_goods_receipt'))

@section('content')
@php
    $directOptions = $productUnits->map(fn ($pu) => [
        'id' => $pu->id,
        'label' => $pu->product->localizedName().' · '.$pu->unit->localizedName().' (×'.\App\Support\Decimal::display($pu->conversion_factor).')',
        'cost' => $pu->product->purchase_cost,
        'track_expiry' => $pu->product->track_expiry,
    ])->values();

    $orderItems = $order
        ? $order->items
            ->filter(fn ($item) => \App\Support\Decimal::compare($item->received_quantity, $item->ordered_quantity) < 0)
            ->map(fn ($item) => [
                'purchase_order_item_id' => $item->id,
                'product_unit_id' => $item->product_unit_id,
                'label' => $item->product->localizedName().' · '.$item->productUnit->unit->localizedName(),
                'quantity' => \App\Support\Decimal::subtract($item->ordered_quantity, $item->received_quantity),
                'unit_cost' => $item->unit_cost,
                'line_discount_amount' => '0.00',
                'track_expiry' => $item->product->track_expiry,
                'batch_number' => '',
                'manufactured_at' => '',
                'expires_at' => '',
            ])->values()
        : collect();

    $initialItems = old('items', $order
        ? $orderItems->map(fn ($item) => collect($item)->except('label')->all())->all()
        : [['product_unit_id' => '', 'quantity' => '1', 'unit_cost' => '', 'line_discount_amount' => '0.00']]);
@endphp

<form method="POST" action="{{ route('purchasing.receipts.store') }}"
      x-data="{
        direct: {{ $order ? 'false' : 'true' }},
        options: @js($directOptions),
        orderOptions: @js($orderItems),
        items: @js($initialItems),
        expenses: @js(old('expenses', [])),
        addItem() { this.items.push({product_unit_id:'', quantity:'1', unit_cost:'', line_discount_amount:'0.00', batch_number:'', manufactured_at:'', expires_at:''}) },
        addExpense() { this.expenses.push({type:'transport', description:'', amount:''}) },
        directMeta(row) { return this.options.find(x => String(x.id) === String(row.product_unit_id)) || {}; },
        orderMeta(index) { return this.orderOptions[index] || {}; },
        setDirectCost(row) { const meta = this.directMeta(row); if (meta.cost && !row.unit_cost) row.unit_cost = meta.cost; }
      }"
      class="space-y-5">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
    @if($order)<input type="hidden" name="purchase_order_id" value="{{ $order->id }}">@endif

    @section('page-errors', '1')

    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
            <ul class="list-disc space-y-1 ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-5">
            <section class="panel p-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-semibold">{{ __('ui.supplier') }}</label>
                        @if($order)
                            <input type="hidden" name="supplier_id" value="{{ $order->supplier_id }}">
                            <div class="field bg-slate-50 dark:bg-slate-900">{{ $order->supplier->name }}</div>
                        @else
                            <select class="field" name="supplier_id" required>
                                <option value="">{{ __('ui.select_supplier') }}</option>
                                @foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string)old('supplier_id')===(string)$supplier->id)>{{ $supplier->name }}</option>@endforeach
                            </select>
                        @endif
                    </div>
                    <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.received_at') }}</label><input class="field" type="datetime-local" name="received_at" value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}" required></div>
                    <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.supplier_invoice_reference') }}</label><input class="field" name="supplier_invoice_reference" value="{{ old('supplier_invoice_reference') }}"></div>
                    <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.receipt_discount') }}</label><input class="field" name="receipt_discount_amount" value="{{ old('receipt_discount_amount','0.00') }}" inputmode="decimal"></div>
                </div>
                @if($order)
                    <div class="mt-4 rounded-2xl bg-brand-50 p-4 text-sm text-brand-800 dark:bg-brand-950/40 dark:text-brand-200">
                        {{ __('ui.receiving_against_po') }} <strong>{{ $order->number }}</strong>
                    </div>
                @else
                    <div class="mt-4 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">{{ __('ui.direct_receipt_notice') }}</div>
                @endif
            </section>

            <section class="panel p-5">
                <div class="flex items-center justify-between gap-3">
                    <div><h3 class="text-lg font-black">{{ __('ui.received_items') }}</h3><p class="mt-1 text-sm text-slate-500">{{ __('ui.received_items_help') }}</p></div>
                    @if(!$order)<button class="btn-secondary" type="button" @click="addItem()">{{ __('ui.add_item') }}</button>@endif
                </div>

                <div class="mt-5 space-y-4">
                    <template x-for="(row,index) in items" :key="index">
                        <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                            <template x-if="!direct">
                                <div>
                                    <input type="hidden" :name="'items['+index+'][purchase_order_item_id]'" x-model="row.purchase_order_item_id">
                                    <div class="mb-3 text-sm font-bold" x-text="orderMeta(index).label"></div>
                                </div>
                            </template>

                            <div class="grid gap-3 lg:grid-cols-[minmax(13rem,1.5fr)_7rem_9rem_8rem_auto]">
                                <template x-if="direct">
                                    <select class="field" :name="'items['+index+'][product_unit_id]'" x-model="row.product_unit_id" @change="setDirectCost(row)" required>
                                        <option value="">{{ __('ui.select_product_unit') }}</option>
                                        @foreach($directOptions as $option)<option value="{{ $option['id'] }}">{{ $option['label'] }}</option>@endforeach
                                    </select>
                                </template>
                                <template x-if="!direct"><div class="field bg-slate-50 dark:bg-slate-900">{{ __('ui.po_item') }}</div></template>
                                <input class="field" :name="'items['+index+'][quantity]'" x-model="row.quantity" inputmode="decimal" placeholder="{{ __('ui.quantity') }}" required>
                                <input class="field" :name="'items['+index+'][unit_cost]'" x-model="row.unit_cost" inputmode="decimal" placeholder="{{ __('ui.unit_cost') }}" required>
                                <input class="field" :name="'items['+index+'][line_discount_amount]'" x-model="row.line_discount_amount" inputmode="decimal" placeholder="{{ __('ui.discount') }}">
                                <button x-show="direct" type="button" class="text-red-600" @click="items.splice(index,1)" :disabled="items.length===1">×</button>
                            </div>

                            <div class="mt-3 grid gap-3 md:grid-cols-3"
                                 x-show="direct ? directMeta(row).track_expiry : orderMeta(index).track_expiry">
                                <input class="field" :name="'items['+index+'][batch_number]'" x-model="row.batch_number" placeholder="{{ __('ui.batch_number') }}">
                                <div><label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.manufactured_date') }}</label><input class="field" type="date" :name="'items['+index+'][manufactured_at]'" x-model="row.manufactured_at"></div>
                                <div><label class="mb-1 block text-xs font-semibold text-slate-500">{{ __('ui.expiry_date') }}</label><input class="field" type="date" :name="'items['+index+'][expires_at]'" x-model="row.expires_at"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </section>

            <section class="panel p-5">
                <div class="flex items-center justify-between gap-3">
                    <div><h3 class="text-lg font-black">{{ __('ui.purchase_expenses') }}</h3><p class="mt-1 text-sm text-slate-500">{{ __('ui.purchase_expenses_help') }}</p></div>
                    <button class="btn-secondary" type="button" @click="addExpense()">{{ __('ui.add_expense') }}</button>
                </div>
                <div class="mt-5 space-y-3">
                    <template x-for="(expense,index) in expenses" :key="index">
                        <div class="grid gap-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-800 md:grid-cols-[10rem_minmax(0,1fr)_9rem_auto]">
                            <select class="field" :name="'expenses['+index+'][type]'" x-model="expense.type">
                                @foreach(\App\Enums\PurchaseExpenseType::cases() as $type)<option value="{{ $type->value }}">{{ __('ui.expense_'.$type->value) }}</option>@endforeach
                            </select>
                            <input class="field" :name="'expenses['+index+'][description]'" x-model="expense.description" placeholder="{{ __('ui.description') }}">
                            <input class="field" :name="'expenses['+index+'][amount]'" x-model="expense.amount" inputmode="decimal" placeholder="{{ __('ui.amount_afn') }}" required>
                            <button type="button" class="text-red-600" @click="expenses.splice(index,1)">×</button>
                        </div>
                    </template>
                    <div x-show="expenses.length===0" class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700">{{ __('ui.no_purchase_expenses') }}</div>
                </div>
            </section>
        </div>

        <aside class="space-y-5">
            @if(auth()->user()->hasPermission('purchases.record_payment'))
                <section class="panel p-5">
                    <h3 class="font-black">{{ __('ui.initial_payment') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ __('ui.initial_payment_help') }}</p>
                    <div class="mt-4 space-y-4">
                        <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.paid_amount') }}</label><input class="field" name="paid_amount" value="{{ old('paid_amount','0.00') }}" inputmode="decimal"></div>
                        <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.payment_method') }}</label><select class="field" name="payment_method"><option value="">{{ __('ui.select_payment_method') }}</option>@foreach(\App\Enums\PurchasePaymentMethod::cases() as $method)<option value="{{ $method->value }}" @selected(old('payment_method')===$method->value)>{{ __('ui.payment_'.$method->value) }}</option>@endforeach</select></div>
                        <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.payment_reference') }}</label><input class="field" name="payment_reference" value="{{ old('payment_reference') }}"></div>
                    </div>
                    <div class="mt-4 rounded-xl bg-slate-50 p-3 text-xs leading-5 text-slate-500 dark:bg-slate-800/60">{{ __('ui.payment_cash_ledger_notice') }}</div>
                </section>
            @else
                <input type="hidden" name="paid_amount" value="0.00">
            @endif

            <section class="panel p-5">
                <div><label class="mb-2 block text-sm font-semibold">{{ __('ui.notes') }}</label><textarea class="field" name="notes" rows="4">{{ old('notes') }}</textarea></div>
                <button class="btn-primary mt-4 w-full" type="submit">{{ __('ui.post_and_receive_stock') }}</button>
                <p class="mt-3 text-xs leading-5 text-slate-500">{{ __('ui.post_receipt_immutable_notice') }}</p>
            </section>
        </aside>
    </div>
</form>
@endsection
