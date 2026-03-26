<?php

namespace App\Http\Controllers;

use App\Models\PointOfSale;
use App\Models\User;
use App\Models\UserPosRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Utenti con ruolo sul POS attivo (o su pos_id in query).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'pos_id' => ['nullable', 'uuid'],
        ]);

        $auth = $request->user();
        $posId = $request->input('pos_id') ?? $auth->activeSessionForCurrentToken()?->pos_id;

        if (! $posId) {
            return response()->json(['data' => []]);
        }

        $this->assertPosBelongsToOrg($posId, $auth->organization_id);

        $userIds = UserPosRole::query()
            ->where('pos_id', $posId)
            ->pluck('user_id');

        $users = User::query()
            ->whereIn('id', $userIds)
            ->where('organization_id', $auth->organization_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json(['data' => $users]);
    }

    private function assertPosBelongsToOrg(string $posId, string $organizationId): void
    {
        if (! PointOfSale::where('id', $posId)->where('organization_id', $organizationId)->exists()) {
            abort(422, 'Il POS non appartiene alla tua organizzazione.');
        }
    }
}
