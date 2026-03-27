<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1, h2, h3 { margin: 0 0 8px 0; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px; vertical-align: top; }
        th { background: #f6f6f6; text-align: left; }
        .totals { margin-top: 14px; text-align: right; }
        .totals p { margin: 2px 0; }
    </style>
</head>
<body>
    <h2>Fattura {{ $invoice->formatted_number }}</h2>
    <p class="muted">Data fattura: {{ $invoice->invoice_date?->format('Y-m-d') }}</p>

    <h3>CEDENTE / PRESTATORE</h3>
    <p>
        <strong>{{ $pos?->name }}</strong><br>
        CF: {{ $pos?->fiscal_code }}<br>
        P.IVA: {{ $pos?->vat_number }}
    </p>

    <h3>CESSIONARIO / COMMITTENTE</h3>
    <p>
        <strong>{{ $invoice->customer_name }}</strong><br>
        CF: {{ $invoice->customer_fiscal_code }}<br>
        @if($invoice->customer_address)
            {{ $invoice->customer_address }}<br>
        @endif
        @if($invoice->customer_city)
            {{ $invoice->customer_city }} {{ $invoice->customer_cap }}<br>
        @endif
        @if($invoice->customer_province)
            {{ $invoice->customer_province }}<br>
        @endif
        {{ $invoice->customer_country }}
    </p>

    <h3>RIGHE</h3>
    <table>
        <thead>
            <tr>
                <th>Descrizione</th>
                <th>Qtà</th>
                <th>Prezzo unit.</th>
                <th>Sconto %</th>
                <th>Imponibile</th>
                <th>IVA</th>
                <th>Totale</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                    <td>{{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                    <td>{{ number_format((float) $item->discount_percent, 2, ',', '.') }}</td>
                    <td>{{ number_format((float) $item->subtotal, 2, ',', '.') }}</td>
                    <td>{{ number_format((float) $item->vat_rate, 2, ',', '.') }}% ({{ number_format((float) $item->vat_amount, 2, ',', '.') }})</td>
                    <td>{{ number_format((float) $item->total, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <p><strong>Subtotale:</strong> € {{ number_format((float) $invoice->subtotal, 2, ',', '.') }}</p>
        <p><strong>IVA:</strong> € {{ number_format((float) $invoice->vat_amount, 2, ',', '.') }}</p>
        <p><strong>Totale:</strong> € {{ number_format((float) $invoice->total, 2, ',', '.') }}</p>
    </div>

    @if(!empty($invoice->notes))
        <p style="margin-top: 18px;"><strong>Note:</strong> {{ $invoice->notes }}</p>
    @endif
</body>
</html>

