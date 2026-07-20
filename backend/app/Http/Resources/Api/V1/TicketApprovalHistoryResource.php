<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketApprovalHistoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'action' => $this->action, 'actor' => $this->whenLoaded('actor', fn () => ['id' => $this->actor->id, 'name' => $this->actor->name]), 'from_status' => $this->from_status, 'to_status' => $this->to_status, 'notes' => $this->notes, 'created_at' => $this->created_at?->toISOString()];
    }
}
