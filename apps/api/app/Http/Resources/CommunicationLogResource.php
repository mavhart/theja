<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'pos_id' => $this->pos_id,
            'patient_id' => $this->patient_id,
            'type' => $this->type,
            'trigger' => $this->trigger,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'error_message' => $this->error_message,
            'provider' => $this->provider,
            'provider_message_id' => $this->provider_message_id,
            'patient' => $this->whenLoaded('patient'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

