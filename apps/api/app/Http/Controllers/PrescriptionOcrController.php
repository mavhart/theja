<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\OcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrescriptionOcrController extends Controller
{
    public function store(Request $request, Patient $patient, OcrService $ocr): JsonResponse
    {
        $request->validate([
            'image_base64' => ['required', 'string'],
        ]);

        try {
            $data = $ocr->parsePrescrizione($request->input('image_base64'));
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json(['data' => $data]);
    }
}
