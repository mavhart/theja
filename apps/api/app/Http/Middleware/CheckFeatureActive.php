<?php

namespace App\Http\Middleware;

use App\Models\DeviceSession;
use App\Models\PointOfSale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocca l'accesso a una route se la feature indicata non è attiva sul POS corrente.
 *
 * Utilizzo nelle route:
 *   Route::middleware('check.feature:ai_analysis_enabled')
 *
 * Il parametro deve corrispondere esattamente al nome della colonna
 * booleana nella tabella points_of_sale.
 */
class CheckFeatureActive
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        // Recupera la sessione attiva dal contesto (impostata da EnforceSessionLimit)
        /** @var DeviceSession|null $session */
        $session = $request->attributes->get('device_session');

        if (!$session) {
            // Fallback: cerca la sessione dal token corrente
            $user  = $request->user();
            $token = $user?->currentAccessToken();
            if ($token) {
                $session = DeviceSession::where('sanctum_token_id', $token->id)
                    ->where('is_active', true)
                    ->first();
            }
        }

        if (!$session) {
            return response()->json(['error' => 'feature_not_active'], 403);
        }

        $pos = PointOfSale::find($session->pos_id);

        if (!$pos || !$pos->{$feature}) {
            return response()->json(['error' => 'feature_not_active'], 403);
        }

        return $next($request);
    }
}
