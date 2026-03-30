<?php

namespace App\Http\Resources;

use App\Helpers\PermissionHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();
        $data['product'] = $this->whenLoaded('product', function () use ($request) {
            /** @var mixed $product */
            $product = $this->resource->product;

            if (! $product) {
                return null;
            }

            $user = $request->user();
            $posId = isset($this->resource->pos_id) ? (string) $this->resource->pos_id : null;

            $arr = $product->toArray();
            if (
                ! $user
                || empty($posId)
                || ! PermissionHelper::userCan($user, 'inventory.view_purchase_price', (string) $posId)
            ) {
                unset($arr['purchase_price']);
            }

            return $arr;
        });
        $data['point_of_sale'] = $this->whenLoaded('pointOfSale');
        $data['pos_name'] = $this->whenLoaded('pointOfSale', fn () => $this->pointOfSale?->name);

        return $data;
    }
}
