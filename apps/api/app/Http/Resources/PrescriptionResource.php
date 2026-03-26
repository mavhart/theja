<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();
        foreach (['visit_date', 'prescribed_at', 'next_recall_at', 'next_recall2_at'] as $d) {
            if (isset($data[$d]) && $data[$d] !== null && ! is_string($data[$d])) {
                $data[$d] = (string) $data[$d];
            }
        }

        return $data;
    }
}
