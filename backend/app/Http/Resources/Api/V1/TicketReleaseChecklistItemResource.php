<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketReleaseChecklistItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'ticket_id' => $this->ticket_id, 'release_plan_id' => $this->release_plan_id, 'label' => $this->label, 'description' => $this->description, 'category' => $this->category, 'is_required' => $this->is_required, 'status' => $this->status, 'completed_by' => $this->whenLoaded('completedBy', fn () => ['id' => $this->completedBy->id, 'name' => $this->completedBy->name]), 'completed_at' => $this->completed_at?->toISOString(), 'notes' => $this->notes, 'version' => $this->version];
    }
}
