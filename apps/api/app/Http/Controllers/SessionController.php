<?php

namespace App\Http\Controllers;

use App\Events\SessionInvalidated;
use App\Models\DeviceSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * GET /api/sessions
     *
     * Lista tutte le sessioni attive dell'utente corrente.
     */
    public function index(Request $request): JsonResponse
    {
        $sessions = DeviceSession::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->with('pointOfSale:id,name,city')
            ->orderByDesc('last_active_at')
            ->get(['id', 'pos_id', 'device_name', 'platform', 'ip_address', 'last_active_at']);

        return response()->json(['data' => $sessions]);
    }

    /**
     * DELETE /api/sessions/{id}
     *
     * Invalida una sessione specifica (logout remoto).
     * Revoca il token Sanctum associato e fa broadcast di SessionInvalidated
     * sul channel privato session.{id} per notificare il client in tempo reale.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $session = DeviceSession::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$session) {
            return response()->json(['message' => 'Sessione non trovata.'], 404);
        }

        $session->update(['is_active' => false]);

        // Revoca il token Sanctum associato
        if ($session->sanctum_token_id) {
            $request->user()
                ->tokens()
                ->where('id', $session->sanctum_token_id)
                ->delete();
        }

        // Notifica il client remoto via WebSocket
        broadcast(new SessionInvalidated($session, 'logged_out_remotely'));

        return response()->json(['message' => 'Sessione invalidata.']);
    }
}
