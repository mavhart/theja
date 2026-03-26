<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; line-height: 1.4; }
        h1 { font-size: 18px; text-align: center; margin: 0 0 16px; }
        .box { border: 1px solid #333; padding: 16px; margin: 12px 0; }
        .muted { color: #555; font-size: 9px; }
        p { margin: 8px 0; }
    </style>
    <title>Certificato idoneità visiva</title>
</head>
<body>
    <h1>Certificato di idoneità visiva</h1>
    <p class="muted" style="text-align:center;">{{ $pos?->name ?? 'Centro ottico' }} @if($pos?->city) — {{ $pos->city }} @endif</p>

    <div class="box">
        <p>Il/La sottoscritto/a certifica che <strong>{{ $patient->last_name }} {{ $patient->first_name }}</strong>
@if($patient->date_of_birth)
            , nato/a il {{ $patient->date_of_birth->format('d/m/Y') }},
@endif
            è stato/a sottoposto/a a visita optometrica in data <strong>{{ $prescription->visit_date->format('d/m/Y') }}</strong>.</p>

        <p>
            <strong>Acuità visiva corretta (riferimento):</strong><br>
            OD: {{ $prescription->visus_od_corrected ?? '—' }} — OS: {{ $prescription->visus_os_corrected ?? '—'}}
            — Binoculare: {{ $prescription->visus_bino_corrected ?? '—' }}
        </p>

        <p>
            In base ai risultati rilevati, il/i soggetto/i risulta/no idoneo/i all&apos;uso degli strumenti ottici prescritti
            nei limiti della presente certificazione, fatti salvi accertamenti specialistici successivi.
        </p>
    </div>

    <p>Data: {{ now()->format('d/m/Y') }}</p>
    <p>Firma operatore / optometrista: {{ $prescription->optician?->name ?? '________________________' }}</p>
</body>
</html>
