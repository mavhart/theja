<?php

namespace App\Http\Resources;

use App\Helpers\PermissionHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();

        // `purchase_price` è sensibile: viene restituito solo con permesso.
        if (array_key_exists('purchase_price', $data)) {
            $user = $request->user();
            $posId = $this->resource->pos_id ?? null;

            $canSeePurchasePrice = (bool) (
                $user
                && ! empty($posId)
                && PermissionHelper::userCan($user, 'inventory.view_purchase_price', (string) $posId)
            );

            if (! $canSeePurchasePrice) {
                unset($data['purchase_price']);
            }
        }

        return $data;
    }
}
