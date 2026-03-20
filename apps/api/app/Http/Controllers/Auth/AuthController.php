<?php

namespace App\Http\Controllers\Auth;

use App\Events\SessionInvalidated;
use App\Helpers\PermissionHelper;
use App\Http\Controllers\Controller;
use App\Models\DeviceSession;
use App\Models\PointOfSale;
use App\Models\UserPosRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/auth/login
     *
     * Valida email/password, crea token Sanctum e restituisce:
     * - token
     * - dati utente
     * - lista POS accessibili
     * - se un solo POS: lo seleziona automaticamente (crea device_session)
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenziali non valide.'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Account disabilitato.'], 401);
        }

        $posList = $user->accessiblePointsOfSale();
        $token   = $user->createToken('api-token')->plainTextToken;

        // Auto-selezione POS se l'utente ha accesso a un solo POS
        if ($posList->count() === 1) {
            $pos     = $posList->first();
            $session = $this->createDeviceSession($user, $pos, $token, $request);

            return response()->json([
                'token'                 => $token,
                'user'                  => $this->formatUser($user),
                'points_of_sale'        => $posList->values(),
                'requires_pos_selection' => false,
                'active_pos'            => $pos,
                'permissions'           => PermissionHelper::permissionsForPos($user, $pos->id),
                'session_id'            => $session->id,
            ]);
        }

        return response()->json([
            'token'                 => $token,
            'user'                  => $this->formatUser($user),
            'points_of_sale'        => $posList->values(),
            'requires_pos_selection' => true,
        ]);
    }

    /**
     * POST /api/auth/select-pos
     *
     * Associa il token corrente al POS scelto e crea la device_session.
     * Se il limite di sessioni simultanee è raggiunto, restituisce 423.
     */
    public function selectPos(Request $request): JsonResponse
    {
        $request->validate([
            'pos_id' => ['required', 'uuid'],
        ]);

        $user = $request->user();
        $pos  = PointOfSale::where('id', $request->pos_id)
            ->where('is_active', true)
            ->first();

        if (!$pos) {
            return response()->json(['message' => 'POS non trovato o non attivo.'], 404);
        }

        // Verifica che l'utente abbia un ruolo in questo POS
        $userPosRole = UserPosRole::where('user_id', $user->id)
            ->where('pos_id', $pos->id)
            ->first();

        if (!$userPosRole) {
            return response()->json(['message' => 'Accesso al POS non autorizzato.'], 403);
        }

        // Controlla limite sessioni simultanee
        $platform    = $request->header('X-Platform', 'web');
        $activeCount = DeviceSession::where('user_id', $user->id)
            ->where('pos_id', $pos->id)
            ->where('platform', $platform)
            ->where('is_active', true)
            ->count();

        if ($activeCount >= $pos->max_concurrent_web_sessions) {
            $activeSessions = DeviceSession::where('user_id', $user->id)
                ->where('pos_id', $pos->id)
                ->where('is_active', true)
                ->get(['id', 'device_name', 'last_active_at', 'platform'])
                ->toArray();

            return response()->json([
                'error'           => 'session_limit_reached',
                'active_sessions' => $activeSessions,
            ], 423);
        }

        $currentToken = $user->currentAccessToken();

        // Rimuove eventuali sessioni precedenti per questo token (re-selezione POS)
        if ($currentToken) {
            DeviceSession::where('sanctum_token_id', $currentToken->id)->delete();
        }

        $session = $this->createDeviceSession($user, $pos, null, $request);

        return response()->json([
            'active_pos'  => $pos,
            'permissions' => PermissionHelper::permissionsForPos($user, $pos->id),
            'session_id'  => $session->id,
        ]);
    }

    /**
     * POST /api/auth/logout
     *
     * Revoca il token corrente e invalida la device_session corrispondente.
     */
    public function logout(Request $request): JsonResponse
    {
        $user  = $request->user();
        $token = $user->currentAccessToken();

        if ($token) {
            $session = DeviceSession::where('sanctum_token_id', $token->id)->first();

            if ($session && $session->is_active) {
                $session->update(['is_active' => false]);
                // Notifica il client via WebSocket prima di revocare il token
                broadcast(new SessionInvalidated($session, 'user_logged_out'));
            }

            $token->delete();
        }

        return response()->json(['message' => 'Logout effettuato.']);
    }

    /**
     * GET /api/auth/me
     *
     * Restituisce utente corrente, POS attivo e permessi.
     */
    public function me(Request $request): JsonResponse
    {
        $user    = $request->user();
        $session = $user->activeSessionForCurrentToken();

        $activePosData  = null;
        $permissions    = [];

        if ($session) {
            $activePosData = $session->pointOfSale;
            $permissions   = PermissionHelper::permissionsForPos($user, $session->pos_id);
        }

        return response()->json([
            'user'        => $this->formatUser($user),
            'active_pos'  => $activePosData,
            'permissions' => $permissions,
            'session_id'  => $session?->id,
        ]);
    }

    // ─── Helpers privati ──────────────────────────────────────────────────────

    /**
     * Crea (o aggiorna) la device_session per il token + POS selezionati.
     * Se $plainTextToken è null usa il token già autenticato nel request.
     */
    private function createDeviceSession(
        \App\Models\User $user,
        PointOfSale $pos,
        ?string $plainTextToken,
        Request $request
    ): DeviceSession {
        // Recupera l'ID del token Sanctum appena creato o corrente
        if ($plainTextToken) {
            // Token appena generato: ricercato per plain-text hash
            $tokenId = $user->tokens()->latest()->first()?->id;
        } else {
            $tokenId = $user->currentAccessToken()?->id;
        }

        return DeviceSession::create([
            'user_id'            => $user->id,
            'pos_id'             => $pos->id,
            'sanctum_token_id'   => $tokenId,
            'device_fingerprint' => $request->header('X-Device-Fingerprint', hash('sha256', $request->ip() . $request->userAgent())),
            'device_name'        => $request->header('X-Device-Name', $request->userAgent() ?? 'Unknown Device'),
            'platform'           => $request->header('X-Platform', 'web'),
            'ip_address'         => $request->ip(),
            'last_active_at'     => now(),
            'is_active'          => true,
        ]);
    }

    private function formatUser(\App\Models\User $user): array
    {
        return [
            'id'              => $user->id,
            'name'            => $user->name,
            'email'           => $user->email,
            'organization_id' => $user->organization_id,
            'is_active'       => $user->is_active,
        ];
    }
}
