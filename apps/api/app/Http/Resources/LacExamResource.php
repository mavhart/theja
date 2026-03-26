<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LacExamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();
        if (isset($data['exam_date']) && $data['exam_date'] !== null && ! is_string($data['exam_date'])) {
            $data['exam_date'] = (string) $data['exam_date'];
        }

        return $data;
    }
}
