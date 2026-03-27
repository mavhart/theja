<?php

namespace App\Http\Resources;

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
        $data['product'] = $this->whenLoaded('product');
        $data['point_of_sale'] = $this->whenLoaded('pointOfSale');
        $data['pos_name'] = $this->whenLoaded('pointOfSale', fn () => $this->pointOfSale?->name);

        return $data;
    }
}
