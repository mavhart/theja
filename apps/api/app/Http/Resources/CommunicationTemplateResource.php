<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationTemplateResource extends JsonResource
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
            'type' => $this->type,
            'trigger' => $this->trigger,
            'subject' => $this->subject,
            'body' => $this->body,
            'variables' => $this->variables ?? [],
            'is_active' => (bool) $this->is_active,
            'language' => $this->language,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

