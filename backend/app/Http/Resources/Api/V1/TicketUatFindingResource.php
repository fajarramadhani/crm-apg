<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketUatFindingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'uat_run_id' => $this->uat_run_id,
            'uat_scenario_id' => $this->uat_scenario_id,
            'reported_by' => $this->reporter ? ['id' => $this->reporter->id, 'name' => $this->reporter->name] : null,
            'assigned_to' => $this->assignee ? ['id' => $this->assignee->id, 'name' => $this->assignee->name] : null,
            'finding_number' => $this->finding_number,
            'title' => $this->title,
            'description' => $this->description,
            'business_impact' => $this->business_impact,
            'severity' => $this->severity,
            'status' => $this->status,
            'resolution_notes' => $this->resolution_notes,
            'resolved_by' => $this->resolver ? ['id' => $this->resolver->id, 'name' => $this->resolver->name] : null,
            'resolved_at' => $this->resolved_at?->toISOString(),
            'verified_by' => $this->verifier ? ['id' => $this->verifier->id, 'name' => $this->verifier->name] : null,
            'verified_at' => $this->verified_at?->toISOString(),
            'comments' => TicketCommentResource::collection($this->whenLoaded('comments')),
            'attachments' => TicketAttachmentResource::collection($this->whenLoaded('attachments')),
            'histories' => TicketUatFindingHistoryResource::collection($this->whenLoaded('histories')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
