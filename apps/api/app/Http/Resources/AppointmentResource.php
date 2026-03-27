<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pos_id' => $this->pos_id,
            'patient_id' => $this->patient_id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'start_at' => $this->start_at?->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'duration_label' => $this->duration_label,
            'notes' => $this->notes,
            'internal_notes' => $this->internal_notes,
            'reminder_sent_at' => $this->reminder_sent_at?->toIso8601String(),
            'order_id' => $this->order_id,
            'sale_id' => $this->sale_id,
            'patient' => $this->whenLoaded('patient'),
            'user' => $this->whenLoaded('user'),
            'order' => $this->whenLoaded('order'),
            'sale' => $this->whenLoaded('sale'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

