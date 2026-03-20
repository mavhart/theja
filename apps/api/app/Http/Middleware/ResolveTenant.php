<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * ResolveTenant Middleware
 *
 * Prima voce della middleware chain su ogni request tenant-aware:
 *   ResolveTenant → EnforceSessionLimit → CheckFeatureActive → auth:sanctum
 *
 * Responsabilità:
 * 1. Legge il Bearer token dalla request
 * 2. Identifica l'utente e la sua Organization tramite PersonalAccessToken
 * 3. Switcha lo schema PostgreSQL: SET search_path TO tenant_{orgId}, public
 * 4. Rende il tenant context disponibile sulla request per i middleware successivi
 * 5. Se non riesce a identificare il tenant → 401
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawToken = $request->bearerToken();

        if (! $rawToken) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $accessToken = PersonalAccessToken::findToken($rawToken);

        if (! $accessToken || ! $accessToken->tokenable) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = $accessToken->tokenable;

        if (! $user->organization_id) {
            return response()->json([
                'message' => 'User has no organization associated.',
            ], 401);
        }

        // Calcola il nome schema: rimuove i trattini dall'UUID
        // Es: 550e8400-e29b-41d4-a716-446655440000 → tenant_550e8400e29b41d4a716446655440000
        $schemaName = 'tenant_' . str_replace('-', '', $user->organization_id);

        // Switcha lo schema PostgreSQL per questa connessione
        DB::statement("SET search_path TO {$schemaName}, public");

        // Rende il context tenant disponibile alla request e ai middleware successivi
        $request->attributes->set('tenant_org_id', $user->organization_id);
        $request->attributes->set('tenant_schema', $schemaName);
        $request->attributes->set('tenant_user', $user);

        return $next($request);
    }
}
