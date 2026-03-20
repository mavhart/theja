<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Middleware\ResolveTenant;

/*
|--------------------------------------------------------------------------
| API Routes — Theja
|--------------------------------------------------------------------------
|
| Middleware chain per ogni route tenant-aware:
|   ResolveTenant → EnforceSessionLimit → CheckFeatureActive → auth:sanctum
|
*/

// Health-check pubblico (no auth)
Route::get('/health', fn () => response()->json(['status' => 'ok']));

// Route protette da ResolveTenant (usate anche nei test)
Route::middleware([ResolveTenant::class])->group(function () {

    // Restituisce il search_path PostgreSQL corrente — usata nei test di ResolveTenant
    Route::get('/tenant/schema', function () {
        $row = DB::selectOne('SHOW search_path');
        return response()->json(['search_path' => $row->search_path ?? '']);
    });

    // Tutte le route tenant-aware verranno aggiunte qui nelle Fasi successive
    // Route::apiResource('organizations', OrganizationController::class);
    // Route::apiResource('points-of-sale', PointOfSaleController::class);
});
