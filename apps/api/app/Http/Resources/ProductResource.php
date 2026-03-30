<?php

namespace App\Http\Resources;

use App\Helpers\PermissionHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();

        // `purchase_price` è sensibile: viene restituito solo se l'utente ha permesso
        // `inventory.view_purchase_price` sul POS attivo.
        $user = $request->user();
        $posId = $user?->activeSessionForCurrentToken()?->pos_id;

        if (! $user || empty($posId) || ! PermissionHelper::userCan(
            $user,
            'inventory.view_purchase_price',
            (string) $posId
        )) {
            unset($data['purchase_price']);
        }

        return $data;
    }
}
