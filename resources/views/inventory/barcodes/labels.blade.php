<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('pos.locales.'.app()->getLocale().'.direction', 'ltr') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.barcode_labels') }} · {{ $barcode->product->localizedName() }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 10mm; color: #000; background: #f3f4f6; font-family: Arial, sans-serif; }
        .toolbar { max-width: 900px; margin: 0 auto 8mm; display: flex; gap: 8px; align-items: center; justify-content: space-between; }
        .toolbar a, .toolbar button { border: 1px solid #cbd5e1; border-radius: 8px; background: white; padding: 8px 12px; cursor: pointer; text-decoration: none; color: #111827; }
        .sheet { max-width: 900px; margin: auto; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 4mm; }
        .label { background: white; border: 1px dashed #9ca3af; min-height: 34mm; padding: 3mm; display: flex; flex-direction: column; justify-content: center; text-align: center; page-break-inside: avoid; }
        .name { font-size: 10pt; font-weight: 700; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .meta { font-size: 8pt; margin-top: 1mm; }
        .barcode { margin-top: 2mm; direction: ltr; color: #000; }
        @media print {
            @page { margin: 4mm; }
            body { background: white; padding: 0; }
            .toolbar { display: none; }
            .sheet { max-width: none; }
            .label { border-color: #d1d5db; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <strong>{{ __('ui.barcode_labels') }} · {{ $barcode->product->localizedName() }}</strong>
        <div>
            <button type="button" onclick="window.print()">{{ __('ui.print') }}</button>
            <a href="{{ route('inventory.products.show', $barcode->product) }}">{{ __('ui.back') }}</a>
        </div>
    </div>

    <main class="sheet">
        @for($i = 0; $i < $quantity; $i++)
            <section class="label">
                <div class="name">{{ $barcode->product->localizedName() }}</div>
                <div class="meta">{{ $barcode->product->sku }} · {{ $barcode->productUnit->unit->symbol ?: $barcode->productUnit->unit->code }} · {{ \App\Support\Money::format($barcode->productUnit->selling_price ?? $barcode->product->selling_price) }}</div>
                <div class="barcode">{!! $barcodeSvg !!}</div>
            </section>
        @endfor
    </main>

    @if($autoprint)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
