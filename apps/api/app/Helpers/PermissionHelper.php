<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\UserPosRole;

class PermissionHelper
{
    /**
     * Verifica se un utente ha un determinato permesso per un POS specifico.
     *
     * Ordine di risoluzione:
     * 1. Override per-utente (es. can_see_purchase_prices)
     * 2. Permessi del ruolo assegnato all'utente in quel POS
     */
    public static function userCan(User $user, string $permission, string $posId): bool
    {
        $userPosRole = UserPosRole::where('user_id', $user->id)
            ->where('pos_id', $posId)
            ->with('role.permissions')
            ->first();

        if (!$userPosRole || !$userPosRole->role) {
            return false;
        }

        // Override granulare: prezzi d'acquisto visibili solo se impostato esplicitamente
        if ($permission === 'inventory.view_purchase_price' && $userPosRole->can_see_purchase_prices) {
            return true;
        }

        return $userPosRole->role->hasPermissionTo($permission);
    }

    /**
     * Restituisce tutti i permessi di un utente per un POS,
     * inclusi eventuali override per-utente.
     *
     * @return string[]
     */
    public static function permissionsForPos(User $user, string $posId): array
    {
        $userPosRole = UserPosRole::where('user_id', $user->id)
            ->where('pos_id', $posId)
            ->with('role.permissions')
            ->first();

        if (!$userPosRole || !$userPosRole->role) {
            return [];
        }

        $permissions = $userPosRole->role->permissions->pluck('name')->toArray();

        if ($userPosRole->can_see_purchase_prices
            && !in_array('inventory.view_purchase_price', $permissions)) {
            $permissions[] = 'inventory.view_purchase_price';
        }

        return array_values(array_unique($permissions));
    }
}
