<?php

namespace App\Http\Controllers;

use App\Models\PointOfSale;
use App\Services\SumUpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SumUpController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pos_id' => ['nullable', 'uuid', 'exists:points_of_sale,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $service = new SumUpService($this->resolvePos($request, $data['pos_id'] ?? null));
        $response = $service->createPayment(
            (float) $data['amount'],
            strtoupper((string) ($data['currency'] ?? 'EUR')),
            (string) ($data['description'] ?? '')
        );

        return response()->json(['data' => $response], 201);
    }

    public function status(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'pos_id' => ['nullable', 'uuid', 'exists:points_of_sale,id'],
        ]);

        $service = new SumUpService($this->resolvePos($request, $data['pos_id'] ?? null));
        $response = $service->getPaymentStatus($id);

        return response()->json(['data' => $response]);
    }

    public function refund(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'pos_id' => ['nullable', 'uuid', 'exists:points_of_sale,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $service = new SumUpService($this->resolvePos($request, $data['pos_id'] ?? null));
        $response = $service->refund($id, (float) $data['amount']);

        return response()->json(['data' => $response]);
    }

    private function resolvePos(Request $request, ?string $posId = null): PointOfSale
    {
        $id = $posId ?: (string) ($request->query('pos_id') ?: $request->user()?->current_pos_id);
        abort_if(empty($id), 422, 'pos_id mancante.');

        return PointOfSale::query()->findOrFail($id);
    }
}

