<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 8px; }
        h2 { font-size: 12px; margin: 12px 0 6px; border-bottom: 1px solid #ccc; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; }
        th { background: #f5f5f5; }
        .muted { color: #555; font-size: 9px; }
    </style>
    <title>Scheda LAC</title>
</head>
<body>
    <h1>Scheda esame LAC</h1>
    <p class="muted">{{ $pos?->name ?? 'Punto vendita' }} @if($pos?->city) — {{ $pos->city }} @endif</p>
    <p><strong>Paziente:</strong> {{ $patient->last_name }} {{ $patient->first_name }}</p>
    <p><strong>Data esame:</strong> {{ $exam->exam_date->format('d/m/Y') }}</p>

    <h2>OD</h2>
    <table>
        <tr><th>R1</th><th>R2</th><th>Media</th><th>Asse R2</th></tr>
        <tr>
            <td>{{ $exam->od_r1 ?? '—' }}</td>
            <td>{{ $exam->od_r2 ?? '—' }}</td>
            <td>{{ $exam->od_media ?? '—' }}</td>
            <td>{{ $exam->od_ax_r2 ?? '—' }}</td>
        </tr>
    </table>

    <h2>OS</h2>
    <table>
        <tr><th>R1</th><th>R2</th><th>Media</th><th>Asse R2</th></tr>
        <tr>
            <td>{{ $exam->os_r1 ?? '—' }}</td>
            <td>{{ $exam->os_r2 ?? '—' }}</td>
            <td>{{ $exam->os_media ?? '—' }}</td>
            <td>{{ $exam->os_ax_r2 ?? '—' }}</td>
        </tr>
    </table>

    <h2>Istruzioni</h2>
    <p>Seguire le indicazioni dello specialista per l&apos;applicazione e la manutenzione delle lenti a contatto. In caso di arrossamento o dolore, sospendere l&apos;uso e contattare il centro.</p>

    <p style="margin-top: 24px;">Operatore: {{ $exam->optician?->name ?? '________________' }} — {{ now()->format('d/m/Y') }}</p>
</body>
</html>
