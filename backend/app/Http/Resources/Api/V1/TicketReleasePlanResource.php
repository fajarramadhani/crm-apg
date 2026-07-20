<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketReleasePlanResource extends JsonResource
{
    public function toArray($request): array
    {
        $technical = $request->user()?->hasPermission('ticket.technical.view') ?? false;

        return ['id' => $this->id, 'ticket_id' => $this->ticket_id, 'version' => $this->version, 'release_owner' => $this->whenLoaded('releaseOwner', fn () => ['id' => $this->releaseOwner->id, 'name' => $this->releaseOwner->name]), 'release_type' => $this->release_type, 'target_environment' => $this->target_environment, 'change_summary' => $this->change_summary, 'technical_summary' => $this->when($technical, $this->technical_summary), 'affected_components' => $this->when($technical, $this->affected_components), 'dependencies' => $this->when($technical, $this->dependencies), 'database_changes' => $this->when($technical, $this->database_changes), 'data_migration_required' => $this->when($technical, $this->data_migration_required), 'downtime_required' => $this->downtime_required, 'estimated_downtime_minutes' => $this->estimated_downtime_minutes, 'proposed_start_at' => $this->proposed_start_at?->toISOString(), 'estimated_duration_minutes' => $this->estimated_duration_minutes, 'validation_steps' => $this->when($technical, $this->validation_steps), 'monitoring_plan' => $this->when($technical, $this->monitoring_plan), 'communication_notes' => $this->communication_notes, 'status' => $this->status, 'lock_version' => $this->lock_version, 'checklist_items' => TicketReleaseChecklistItemResource::collection($this->whenLoaded('checklistItems'))];
    }
}
