<?php

namespace App\Http\Middleware;

use App\Models\DeviceSession;
use App\Models\PointOfSale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controlla che il numero di sessioni attive per (user, pos, platform)
 * non superi il limite configurato in points_of_sale.max_concurrent_web_sessions.
 *
 * Se nessuna device_session è associata al token corrente, il middleware
 * lascia passare la request (fase di login ancora in corso).
 *
 * In caso di superamento del limite → HTTP 423 con lista sessioni attive.
 */
class EnforceSessionLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $token = $user->currentAccessToken();

        if (!$token) {
            return $next($request);
        }

        $session = DeviceSession::where('sanctum_token_id', $token->id)
            ->where('is_active', true)
            ->first();

        // Nessuna sessione associata al token → login/select-pos non ancora completato
        if (!$session) {
            return $next($request);
        }

        $pos = PointOfSale::find($session->pos_id);

        if (!$pos) {
            return $next($request);
        }

        $activeCount = DeviceSession::where('user_id', $user->id)
            ->where('pos_id', $session->pos_id)
            ->where('platform', $session->platform)
            ->where('is_active', true)
            ->count();

        if ($activeCount > $pos->max_concurrent_web_sessions) {
            $activeSessions = DeviceSession::where('user_id', $user->id)
                ->where('pos_id', $session->pos_id)
                ->where('is_active', true)
                ->orderByDesc('last_active_at')
                ->get(['id', 'device_name', 'last_active_at', 'platform'])
                ->toArray();

            return response()->json([
                'error'           => 'session_limit_reached',
                'active_sessions' => $activeSessions,
            ], 423);
        }

        // Aggiorna last_active_at e passa il contesto alle route downstream
        $session->update(['last_active_at' => now()]);
        $request->attributes->set('device_session', $session);

        return $next($request);
    }
}
