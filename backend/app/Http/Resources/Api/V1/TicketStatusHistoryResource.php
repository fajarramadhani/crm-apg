<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'from_status' => $this->from_status, 'to_status' => $this->to_status, 'action' => $this->action, 'actor' => ['id' => $this->actor_id, 'name' => $this->actor?->name], 'actor_role' => $this->actor_role, 'notes' => $this->notes, 'metadata' => $this->metadata, 'created_at' => $this->created_at?->toISOString()];
    }
}
