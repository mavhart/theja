<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 5px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
    <title>DDT trasferimento</title>
</head>
<body>
    <h1>Documento di Trasporto (DDT)</h1>
    <p><strong>Numero:</strong> {{ $number }}</p>
    <p><strong>Data:</strong> {{ $date }}</p>

    <p><strong>Mittente (POS):</strong> {{ $transfer->fromPos?->name }} — {{ $transfer->fromPos?->city }}</p>
    <p><strong>Destinatario (POS):</strong> {{ $transfer->toPos?->name }} — {{ $transfer->toPos?->city }}</p>

    <table>
        <tr>
            <th>Prodotto</th>
            <th>Categoria</th>
            <th>Quantità</th>
        </tr>
        <tr>
            <td>{{ $transfer->product?->brand }} {{ $transfer->product?->model }}</td>
            <td>{{ $transfer->product?->category }}</td>
            <td>{{ $transfer->quantity }}</td>
        </tr>
    </table>
</body>
</html>
