<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketApprovalStepResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'step_type' => $this->step_type, 'sequence' => $this->sequence, 'status' => $this->status, 'approver' => $this->whenLoaded('approver', fn () => ['id' => $this->approver->id, 'name' => $this->approver->name]), 'assigned_at' => $this->assigned_at?->toISOString(), 'acted_at' => $this->acted_at?->toISOString(), 'decision_notes' => $this->decision_notes, 'version' => $this->version];
    }
}
