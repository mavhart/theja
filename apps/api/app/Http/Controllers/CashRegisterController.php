<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterSession;
use App\Models\PointOfSale;
use App\Models\Sale;
use App\Helpers\PermissionHelper;
use App\Services\VirtualCashRegisterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashRegisterController extends Controller
{
    public function session(Request $request, VirtualCashRegisterService $service): JsonResponse
    {
        $pos = $this->resolvePos($request);
        $session = $service->getCurrentSession($pos);

        return response()->json(['data' => $session]);
    }

    public function open(Request $request, VirtualCashRegisterService $service): JsonResponse
    {
        $data = $request->validate([
            'pos_id' => ['nullable', 'uuid', 'exists:points_of_sale,id'],
            'opening_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $pos = $this->resolvePos($request, $data['pos_id'] ?? null);
        $session = $service->openSession($pos, $request->user(), (float) $data['opening_amount']);

        return response()->json(['data' => $session], 201);
    }

    public function close(Request $request, VirtualCashRegisterService $service): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['nullable', 'uuid', 'exists:cash_register_sessions,id'],
            'pos_id' => ['nullable', 'uuid', 'exists:points_of_sale,id'],
            'closing_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $pos = $this->resolvePos($request, $data['pos_id'] ?? null);
        $session = isset($data['session_id'])
            ? CashRegisterSession::query()->findOrFail($data['session_id'])
            : $service->getCurrentSession($pos);

        if (! $session) {
            abort(404, 'Nessuna sessione aperta trovata.');
        }

        $closed = $service->closeSession($session, (float) $data['closing_amount']);
        if (isset($data['notes'])) {
            $closed->notes = $data['notes'];
            $closed->save();
        }

        return response()->json(['data' => $closed->fresh()]);
    }

    public function summary(Request $request, VirtualCashRegisterService $service): JsonResponse
    {
        $pos = $this->resolvePos($request);
        $summary = $service->summarizeCurrentSession($pos);

        return response()->json(['data' => $summary]);
    }

    public function fiscalDocument(Request $request, VirtualCashRegisterService $service): JsonResponse
    {
        $data = $request->validate([
            'sale_id' => ['required', 'uuid', 'exists:sales,id'],
            'type' => ['nullable', 'in:scontrino,ricevuta,fattura_accompagnatoria'],
        ]);

        $sale = Sale::query()->findOrFail($data['sale_id']);
        $receipt = $service->sendFiscalDocument($sale, (string) ($data['type'] ?? 'scontrino'));

        return response()->json(['data' => $receipt], 201);
    }

    private function resolvePos(Request $request, ?string $posId = null): PointOfSale
    {
        $id = $posId ?: (string) ($request->query('pos_id') ?: $request->user()?->current_pos_id);
        abort_if(empty($id), 422, 'pos_id mancante.');

        $pos = PointOfSale::query()->findOrFail($id);
        $user = $request->user();
        abort_unless($user && PermissionHelper::userCan($user, 'cash_register.access', $pos->id), 403, 'Non autorizzato.');

        return $pos;
    }
}

