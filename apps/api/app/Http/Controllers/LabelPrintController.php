<?php

namespace App\Http\Controllers;

use App\Services\LabelPrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabelPrintController extends Controller
{
    public function print(Request $request, LabelPrintService $service): JsonResponse
    {
        $data = $request->validate([
            'product_ids'    => ['required', 'array', 'min:1'],
            'product_ids.*'  => ['required', 'uuid', 'exists:products,id'],
            'template_id'    => ['required', 'uuid', 'exists:label_templates,id'],
            'start_position' => ['nullable', 'integer', 'min:1'],
            'copies'         => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $copies = (int) ($data['copies'] ?? 1);
        $productIds = [];
        for ($i = 0; $i < $copies; $i++) {
            foreach ($data['product_ids'] as $id) {
                $productIds[] = (string) $id;
            }
        }

        $pdf = $service->generatePdf(
            $productIds,
            (string) $data['template_id'],
            (int) ($data['start_position'] ?? 1),
        );

        return response()->json([
            'pdf_base64'   => $pdf,
            'filename'     => 'etichette_'.now()->format('Ymd_His').'.pdf',
            'label_count'  => count($productIds),
            'pages'        => null,
        ]);
    }
}
