<?php

namespace App\Services;

use App\Models\StockTransferRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class DdtService
{
    public function generate(StockTransferRequest $transfer): string
    {
        $transfer->loadMissing('fromPos.organization', 'toPos', 'product');
        $orgId = (string) $transfer->fromPos->organization_id;
        $year = now()->format('Y');
        $seq = StockTransferRequest::query()
            ->whereNotNull('ddt_number')
            ->count() + 1;

        $number = sprintf('DDT-%s-%06d', $year, $seq);

        $pdf = Pdf::loadView('pdf.ddt_transfer', [
            'transfer' => $transfer,
            'number'   => $number,
            'date'     => now()->format('d/m/Y'),
        ]);
        $pdf->setPaper('a4');

        $path = "ddt/{$orgId}/{$number}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        $transfer->ddt_number = $number;
        $transfer->ddt_pdf_path = $path;
        $transfer->save();

        return $path;
    }
}
