<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; }
        .page {
            position: relative;
            width: 595pt;
            height: 842pt;
            page-break-after: always;
        }
        .label {
            position: absolute;
            box-sizing: border-box;
            border: 0.4pt dashed #ddd;
            overflow: hidden;
            padding: 2pt;
        }
        .line { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.1; }
    </style>
    <title>Etichette</title>
</head>
<body>
@php
    $cols = max(1, (int) $template->cols);
    $rows = max(1, (int) $template->rows);
    $labelsPerPage = $labels_per_page;
    $w = (float) $template->label_width_mm * $mm_to_pt;
    $h = (float) $template->label_height_mm * $mm_to_pt;
    $mt = (float) $template->margin_top_mm * $mm_to_pt;
    $ml = (float) $template->margin_left_mm * $mm_to_pt;
    $sh = (float) $template->spacing_h_mm * $mm_to_pt;
    $sv = (float) $template->spacing_v_mm * $mm_to_pt;
    $fields = is_array($template->fields) ? $template->fields : [];

    $full = array_fill(0, $start_index, null);
    foreach ($labels as $l) { $full[] = $l; }
    $pages = (int) ceil(count($full) / $labelsPerPage);
@endphp

@for ($page = 0; $page < $pages; $page++)
    <div class="page">
        @for ($i = 0; $i < $labelsPerPage; $i++)
            @php
                $global = $page * $labelsPerPage + $i;
                $cell = $full[$global] ?? null;
                $r = intdiv($i, $cols);
                $c = $i % $cols;
                $x = $ml + $c * ($w + $sh);
                $y = $mt + $r * ($h + $sv);
            @endphp
            <div class="label" style="left: {{ $x }}pt; top: {{ $y }}pt; width: {{ $w }}pt; height: {{ $h }}pt;">
                @if($cell)
                    @foreach($fields as $f)
                        @php
                            $visible = (bool) ($f['visible'] ?? true);
                            if (! $visible) continue;
                            $key = (string) ($f['key'] ?? '');
                            $font = (int) ($f['font_size'] ?? 8);
                            $p = $cell['product'];
                        @endphp
                        @if($key === 'barcode')
                            <div>{!! $cell['barcode_svg'] !!}</div>
                        @elseif($key === 'barcode_number')
                            <div class="line" style="font-size: {{ $font }}pt;">{{ $cell['barcode_number'] }}</div>
                        @elseif($key === 'brand')
                            <div class="line" style="font-size: {{ $font }}pt;">{{ $p->brand }}</div>
                        @elseif($key === 'model')
                            <div class="line" style="font-size: {{ $font }}pt;">{{ $p->model }}</div>
                        @elseif($key === 'caliber_bridge')
                            <div class="line" style="font-size: {{ $font }}pt;">{{ $p->caliber }} / {{ $p->bridge }}</div>
                        @elseif($key === 'color')
                            <div class="line" style="font-size: {{ $font }}pt;">{{ $p->color }}</div>
                        @elseif($key === 'sale_price')
                            <div class="line" style="font-size: {{ $font }}pt; font-weight: bold;">€ {{ $p->sale_price }}</div>
                        @elseif($key === 'supplier')
                            <div class="line" style="font-size: {{ $font }}pt;">{{ $p->supplier?->company_name ?? $p->supplier?->last_name }}</div>
                        @endif
                    @endforeach
                @endif
            </div>
        @endfor
    </div>
@endfor
</body>
</html>
