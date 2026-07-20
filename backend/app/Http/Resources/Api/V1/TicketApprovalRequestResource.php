<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketApprovalRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'ticket' => $this->whenLoaded('ticket', fn () => ['id' => $this->ticket->id, 'ticket_number' => $this->ticket->ticket_number, 'title' => $this->ticket->title, 'status' => $this->ticket->status->value]), 'cycle_number' => $this->cycle_number, 'status' => $this->status, 'summary' => $this->summary, 'business_impact' => $this->business_impact, 'release_risk_level' => $this->release_risk_level, 'proposed_release_at' => $this->proposed_release_at?->toISOString(), 'revision_reason' => $this->revision_reason, 'steps' => TicketApprovalStepResource::collection($this->whenLoaded('steps')), 'histories' => TicketApprovalHistoryResource::collection($this->whenLoaded('histories')), 'version' => $this->version, 'requested_at' => $this->requested_at?->toISOString(), 'completed_at' => $this->completed_at?->toISOString()];
    }
}
