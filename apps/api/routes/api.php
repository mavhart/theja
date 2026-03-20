<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Middleware\CheckFeatureActive;
use App\Http\Middleware\EnforceSessionLimit;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Theja
|--------------------------------------------------------------------------
|
| Middleware chain per le route tenant-aware:
|   auth:sanctum → ResolveTenant → EnforceSessionLimit → [check.feature:X]
|
*/

// ─── Pubbliche (nessuna auth) ─────────────────────────────────────────────────

Route::get('/health', fn () => response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]));

// Webhook Stripe — nessun middleware auth (Stripe usa firma HMAC, non token)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

// ─── Autenticazione ───────────────────────────────────────────────────────────

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/auth/select-pos', [AuthController::class, 'selectPos']);
    Route::post('/auth/logout',     [AuthController::class, 'logout']);
    Route::get('/auth/me',          [AuthController::class, 'me']);

    Route::get('/sessions',           [SessionController::class, 'index']);
    Route::delete('/sessions/{id}',   [SessionController::class, 'destroy']);
});

// ─── Route tenant-aware ──────────────────────────────────────────────────────
// auth:sanctum → ResolveTenant (switcha schema PostgreSQL) → EnforceSessionLimit

Route::middleware(['auth:sanctum', ResolveTenant::class, EnforceSessionLimit::class])->group(function () {

    // Usata nei test di ResolveTenant — restituisce il search_path corrente
    Route::get('/tenant/schema', function () {
        $row = DB::selectOne('SHOW search_path');
        return response()->json(['search_path' => $row->search_path ?? '']);
    });

    // Esempio route con feature flag — protetta da check.feature:ai_analysis_enabled
    Route::middleware([CheckFeatureActive::class . ':ai_analysis_enabled'])->group(function () {
        Route::get('/ai/analyze', fn () => response()->json(['status' => 'ai_ready']));
    });

    // ─── Future route tenant-aware (Fase 2+) ────────────────────────────────
    // Route::apiResource('organizations', OrganizationController::class);
    // Route::apiResource('points-of-sale', PointOfSaleController::class);
});
