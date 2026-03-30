<?php

namespace App\Http\Resources;

use App\Helpers\PermissionHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();

        // `purchase_price` è sensibile: viene mostrato solo se l'utente ha
        // il permesso `inventory.view_purchase_price` sul POS attivo.
        $user = $request->user();
        $posId = $user?->activeSessionForCurrentToken()?->pos_id;

        $canSeePurchasePrice = (bool) (
            $user
            && ! empty($posId)
            && PermissionHelper::userCan($user, 'inventory.view_purchase_price', (string) $posId)
        );

        if (! $canSeePurchasePrice && isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as &$itemRow) {
                if (is_array($itemRow) && array_key_exists('purchase_price', $itemRow)) {
                    unset($itemRow['purchase_price']);
                }
            }
            unset($itemRow);
        }

        return $data;
    }
}