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
        .sig { margin-top: 32px; }
    </style>
    <title>Referto visita</title>
</head>
<body>
    <h1>Referto visita optometrica</h1>
    <p class="muted">{{ $pos?->name ?? 'Punto vendita' }} @if($pos?->city) — {{ $pos->city }} @endif</p>
    <p><strong>Paziente:</strong> {{ $patient->last_name }} {{ $patient->first_name }}
        @if($patient->date_of_birth) — nato il {{ $patient->date_of_birth->format('d/m/Y') }} @endif
    </p>
    <p><strong>Data visita:</strong> {{ $prescription->visit_date->format('d/m/Y') }}</p>

    <h2>Valori lontano (OD / OS)</h2>
    <table>
        <tr><th></th><th>Sfera</th><th>Cilindro</th><th>Asse</th><th>Add</th></tr>
        <tr>
            <td><strong>OD</strong></td>
            <td>{{ $prescription->od_sphere_far ?? '—' }}</td>
            <td>{{ $prescription->od_cylinder_far ?? '—' }}</td>
            <td>{{ $prescription->od_axis_far ?? '—' }}</td>
            <td>{{ $prescription->od_addition_far ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>OS</strong></td>
            <td>{{ $prescription->os_sphere_far ?? '—' }}</td>
            <td>{{ $prescription->os_cylinder_far ?? '—' }}</td>
            <td>{{ $prescription->os_axis_far ?? '—' }}</td>
            <td>{{ $prescription->os_addition_far ?? '—' }}</td>
        </tr>
    </table>

    <h2>Visus</h2>
    <table>
        <tr><th></th><th>OD</th><th>OS</th><th>Binoculare</th></tr>
        <tr>
            <td>Naturale</td>
            <td>{{ $prescription->visus_od_natural ?? '—' }}</td>
            <td>{{ $prescription->visus_os_natural ?? '—' }}</td>
            <td>{{ $prescription->visus_bino_natural ?? '—' }}</td>
        </tr>
        <tr>
            <td>Corretto</td>
            <td>{{ $prescription->visus_od_corrected ?? '—' }}</td>
            <td>{{ $prescription->visus_os_corrected ?? '—' }}</td>
            <td>{{ $prescription->visus_bino_corrected ?? '—' }}</td>
        </tr>
    </table>

    @if($prescription->notes)
        <h2>Note</h2>
        <p>{{ $prescription->notes }}</p>
    @endif

    <div class="sig">
        <p>Optometrista / operatore: {{ $prescription->optician?->name ?? '________________' }}</p>
        <p>Data documento: {{ now()->format('d/m/Y') }}</p>
    </div>
</body>
</html>
