<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifica il tenant (Organization) dall'utente autenticato via Sanctum
 * e imposta il PostgreSQL search_path sul relativo schema.
 *
 * Deve essere usato DOPO auth:sanctum nel gruppo middleware, poiché si basa
 * su $request->user() per ottenere l'utente già autenticato.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user->organization_id) {
            return response()->json(['message' => 'User has no organization associated.'], 401);
        }

        // Costruisce il nome dello schema: tenant_{uuid_senza_trattini}
        $schemaName = 'tenant_' . str_replace('-', '', $user->organization_id);

        DB::statement("SET search_path TO {$schemaName}, public");

        // Rende disponibile il contesto tenant alle route downstream
        $request->attributes->set('tenant_org_id', $user->organization_id);
        $request->attributes->set('tenant_schema', $schemaName);
        $request->attributes->set('tenant_user', $user);

        return $next($request);
    }
}
