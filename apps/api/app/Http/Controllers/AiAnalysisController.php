<?php

namespace App\Http\Controllers;

use App\Models\PointOfSale;
use App\Services\AiAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAnalysisController extends Controller
{
    public function trends(Request $request, AiAnalysisService $service): JsonResponse
    {
        $pos = $this->resolvePos($request);

        return response()->json(['data' => $service->analyzeTrends($pos)]);
    }

    public function forecastReorders(Request $request, AiAnalysisService $service): JsonResponse
    {
        $pos = $this->resolvePos($request);

        return response()->json(['data' => $service->forecastReorders($pos)]);
    }

    public function revenueAnalysis(Request $request, AiAnalysisService $service): JsonResponse
    {
        $pos = $this->resolvePos($request);

        return response()->json(['data' => $service->analyzeRevenue($pos)]);
    }

    public function opportunities(Request $request, AiAnalysisService $service): JsonResponse
    {
        $pos = $this->resolvePos($request);

        return response()->json(['data' => $service->findOpportunities($pos)]);
    }

    private function resolvePos(Request $request): PointOfSale
    {
        $id = (string) ($request->query('pos_id') ?: $request->user()?->current_pos_id);
        abort_if(empty($id), 422, 'pos_id mancante.');

        return PointOfSale::query()->findOrFail($id);
    }
}

