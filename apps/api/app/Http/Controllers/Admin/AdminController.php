<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\PermissionHelper;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PointOfSale;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserPosRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function __construct()
    {
        // Ruolo globale non tenant
        $this->middleware('role:super_admin');
    }

    public function organizations(Request $request): JsonResponse
    {
        $orgs = Organization::query()
            ->with('subscription')
            ->withCount('pointsOfSale')
            ->with('pointsOfSale')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $orgs->map(fn (Organization $org) => [
                'id' => $org->id,
                'name' => $org->name,
                'pos_count' => (int) $org->points_of_sale_count,
                'subscription_status' => $org->subscription?->status,
                'features' => [
                    'ai_analysis_enabled' => (bool) ($org->pointsOfSale->first()?->ai_analysis_enabled ?? false),
                    'virtual_cash_register_enabled' => (bool) ($org->pointsOfSale->first()?->virtual_cash_register_enabled ?? false),
                ],
            ])->values(),
        ]);
    }

    public function storeOrganization(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'owner_password' => ['nullable', 'string', 'min:8'],
            'pos_count' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $posCount = (int) ($data['pos_count'] ?? 1);

        $org = DB::transaction(function () use ($data, $posCount) {
            $organization = Organization::create([
                'name' => $data['name'],
                'is_active' => true,
            ]);

            // Crea POS
            for ($i = 0; $i < $posCount; $i++) {
                PointOfSale::create([
                    'organization_id' => $organization->id,
                    'name' => 'POS '.($i + 1),
                    'is_active' => true,
                    'max_concurrent_web_sessions' => 5,
                    'max_mobile_devices' => 0,
                    'virtual_cash_register_enabled' => false,
                    'cash_register_hardware_configured' => false,
                    'rt_provider' => null,
                    'rt_credentials' => null,
                    'sumup_api_key' => null,
                    'ai_analysis_enabled' => false,
                ]);
            }

            // Crea utente owner (ruolo tenant: org_owner)
            $password = (string) ($data['owner_password'] ?? 'password');
            $user = User::create([
                'organization_id' => $organization->id,
                'name' => (string) ($data['owner_name'] ?? 'Owner '.$organization->name),
                'email' => $data['owner_email'],
                'password' => Hash::make($password),
                'is_active' => true,
            ]);

            $role = Role::where('name', 'org_owner')->firstOrFail();
            $ownerPos = PointOfSale::query()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->firstOrFail();

            UserPosRole::create([
                'user_id' => $user->id,
                'pos_id' => $ownerPos->id,
                'role_id' => $role->id,
                'can_see_purchase_prices' => true,
            ]);

            return $organization;
        });

        return response()->json([
            'data' => [
                'id' => $org->id,
                'name' => $org->name,
            ],
        ], 201);
    }

    public function updateFeatures(Request $request, Organization $org): JsonResponse
    {
        $data = $request->validate([
            'features' => ['required', 'array'],
            'features.ai_analysis_enabled' => ['nullable', 'boolean'],
            'features.virtual_cash_register_enabled' => ['nullable', 'boolean'],
        ]);

        $features = $data['features'] ?? [];

        $allowed = [
            'ai_analysis_enabled',
            'virtual_cash_register_enabled',
        ];

        $payload = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $features)) {
                $payload[$key] = (bool) $features[$key];
            }
        }

        if (empty($payload)) {
            return response()->json(['message' => 'Nessuna feature valida.' ], 422);
        }

        PointOfSale::query()
            ->where('organization_id', $org->id)
            ->update($payload);

        return response()->json(['message' => 'Features aggiornate.' ]);
    }

    public function stats(Request $request): JsonResponse
    {
        // Calcoli globali su più tenant: saltiamo i dettagli su privacy, sono solo numeri aggregati.
        $orgTotal = Organization::query()->count();
        $posActive = PointOfSale::query()->where('is_active', true)->count();

        $revenue = 0.0;
        foreach (Organization::query()->pluck('id') as $orgId) {
            $schemaName = 'tenant_' . str_replace('-', '', (string) $orgId);
            DB::statement("SET search_path TO {$schemaName}, public");

            $revenue += (float) DB::table('sales')
                ->whereIn('status', ['confirmed', 'delivered'])
                ->sum('total_amount');
        }
        DB::statement('SET search_path TO public');

        return response()->json([
            'data' => [
                'org_total' => $orgTotal,
                'pos_active' => $posActive,
                'revenue_total' => $revenue,
            ],
        ]);
    }
}

