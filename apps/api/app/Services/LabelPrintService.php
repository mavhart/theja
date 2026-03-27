<?php

namespace App\Services;

use App\Models\LabelTemplate;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;

class LabelPrintService
{
    public function __construct(private readonly BarcodeService $barcodeService) {}

    /**
     * @param  array<int, string>  $productIds
     */
    public function generatePdf(array $productIds, string $templateId, int $startPosition = 1): string
    {
        $template = LabelTemplate::query()->findOrFail($templateId);
        $products = Product::query()
            ->with('supplier')
            ->whereIn('id', $productIds)
            ->get()
            ->values();

        $labelsPerPage = max(1, (int) $template->cols * (int) $template->rows);
        $start = max(1, $startPosition);
        $startIndex = $start - 1;

        $labels = [];
        foreach ($products as $p) {
            $code = (string) ($p->barcode ?: $this->barcodeService->generateEan13($p->id));
            $svg = $this->barcodeService->isValidEan13($code)
                ? $this->barcodeService->generate($code, 'EAN13')
                : $this->barcodeService->generateCode128($code);

            $labels[] = [
                'product' => $p,
                'barcode_svg' => $svg,
                'barcode_number' => $code,
            ];
        }

        $pdf = Pdf::loadView('pdf.labels_sheet', [
            'template'       => $template,
            'labels'         => $labels,
            'start_index'    => $startIndex,
            'labels_per_page'=> $labelsPerPage,
            'mm_to_pt'       => 2.835,
        ]);
        $pdf->setPaper('a4');

        return base64_encode($pdf->output());
    }
}
