<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('pos.locales.'.app()->getLocale().'.direction', 'ltr') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.receipt') }} {{ $sale->number }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: {{ $embedded ? '#f8fafc' : '#f3f4f6' }}; color: #000; font-family: Arial, sans-serif; }
        .toolbar { width: min(100%, 520px); margin: 16px auto; display: flex; justify-content: space-between; gap: 8px; }
        .toolbar a, .toolbar button { border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; color: #111827; padding: 8px 12px; text-decoration: none; cursor: pointer; }
        .receipt { width: {{ $shop->receipt_size === '57mm' ? '57mm' : '80mm' }}; margin: 0 auto 20px; background: #fff; padding: 3mm; font-size: {{ $shop->receipt_size === '57mm' ? '9px' : '11px' }}; line-height: 1.35; }
        .center { text-align: center; }
        .shop { font-size: 1.35em; font-weight: 800; }
        .muted { color: #444; }
        .rule { border-top: 1px dashed #000; margin: 2mm 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1mm 0; vertical-align: top; }
        .num { text-align: end; white-space: nowrap; }
        .total td { font-weight: 800; font-size: 1.08em; padding-top: 2mm; }
        @media print {
            @page { size: {{ $shop->receipt_size === '57mm' ? '57mm' : '80mm' }} auto; margin: 0; }
            body { background: #fff; }
            .toolbar { display: none; }
            .receipt { margin: 0; width: {{ $shop->receipt_size === '57mm' ? '57mm' : '80mm' }}; }
        }
    </style>
</head>
<body>
    @unless($embedded)
        <div class="toolbar">
            <a href="{{ route('sales.show', $sale) }}">{{ __('ui.back') }}</a>
            <button type="button" onclick="window.print()">{{ __('ui.print_receipt') }}</button>
        </div>
    @endunless

    <main class="receipt">
        <header class="center">
            <div class="shop">{{ $shop->shop_name }}</div>
            @if($shop->address)<div>{{ $shop->address }}</div>@endif
            @if($shop->phone)<div>{{ $shop->phone }}</div>@endif
        </header>

        <div class="rule"></div>
        <div>{{ __('ui.receipt') }}: <strong>{{ $sale->number }}</strong></div>
        <div>{{ __('ui.date') }}: {{ $sale->sold_at->format('Y-m-d H:i') }}</div>
        <div>{{ __('ui.cashier') }}: {{ $sale->cashier?->name }}</div>
        @if($sale->terminal)<div>{{ __('ui.terminal') }}: {{ $sale->terminal->name }}</div>@endif
        @if($sale->customer_name_snapshot)<div>{{ __('ui.customer') }}: {{ $sale->customer_name_snapshot }}</div>@endif
        <div class="rule"></div>

        <table>
            <thead>
                <tr>
                    <th>{{ __('ui.product') }}</th>
                    <th class="num">{{ __('ui.quantity') }}</th>
                    <th class="num">{{ __('ui.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $item)
                    <tr>
                        <td>
                            {{ $item->product_name_snapshot }}
                            <div class="muted">{{ $item->unit_name_snapshot }} × {{ \App\Support\Money::format($item->unit_price) }}</div>
                        </td>
                        <td class="num">{{ \App\Support\Decimal::display($item->quantity) }}</td>
                        <td class="num">{{ \App\Support\Money::format($item->line_net_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="rule"></div>
        <table>
            <tr><td>{{ __('ui.subtotal') }}</td><td class="num">{{ \App\Support\Money::format($sale->subtotal) }}</td></tr>
            @if(\App\Support\Decimal::compare($sale->line_discount_total, '0') > 0)
                <tr><td>{{ __('ui.line_discount') }}</td><td class="num">-{{ \App\Support\Money::format($sale->line_discount_total) }}</td></tr>
            @endif
            @if(\App\Support\Decimal::compare($sale->sale_discount_amount, '0') > 0)
                <tr><td>{{ __('ui.sale_discount') }}</td><td class="num">-{{ \App\Support\Money::format($sale->sale_discount_amount) }}</td></tr>
            @endif
            <tr class="total"><td>{{ __('ui.net_total') }}</td><td class="num">{{ \App\Support\Money::format($sale->net_total) }}</td></tr>
            @if(\App\Support\Decimal::compare($sale->returned_total, '0') > 0)
                <tr><td>{{ __('ui.returned_total') }}</td><td class="num">-{{ \App\Support\Money::format($sale->returned_total) }}</td></tr>
            @endif
            <tr><td>{{ __('ui.paid') }}</td><td class="num">{{ \App\Support\Money::format($sale->paid_amount) }}</td></tr>
            <tr><td>{{ __('ui.balance_due') }}</td><td class="num">{{ \App\Support\Money::format($sale->balance_due) }}</td></tr>
        </table>

        @if($sale->payments->isNotEmpty())
            <div class="rule"></div>
            @foreach($sale->payments as $payment)
                <div>{{ $payment->paymentMethod->localizedName() }}: {{ \App\Support\Money::format($payment->applied_amount) }}</div>
            @endforeach
        @endif

        @if($sale->notes)
            <div class="rule"></div>
            <div>{{ __('ui.notes') }}: {{ $sale->notes }}</div>
        @endif

        <div class="rule"></div>
        <div class="center">{{ __('ui.thank_you') }}</div>
    </main>

    @if($autoprint)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
