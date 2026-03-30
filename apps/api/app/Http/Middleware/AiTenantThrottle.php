<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AiTenantThrottle
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $tenantOrgId = $user?->organization_id;

        if (! $tenantOrgId) {
            abort(401);
        }

        $key = 'ai:tenant:'.$tenantOrgId;
        $maxAttempts = 5;
        $decaySeconds = 60; // 1 minuto

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()
                ->json(['message' => 'Too many AI requests. Riprovare tra '. $retryAfter .' secondi.'], 429)
                ->header('Retry-After', (string) $retryAfter);
        }

        RateLimiter::hit($key, $decaySeconds);

        return $next($request);
    }
}

