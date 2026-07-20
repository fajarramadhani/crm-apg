<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketRollbackPlanResource extends JsonResource
{
    public function toArray($request): array
    {
        $technical = $request->user()?->hasPermission('ticket.technical.view') ?? false;

        return ['id' => $this->id, 'ticket_id' => $this->ticket_id, 'release_plan_id' => $this->release_plan_id, 'version' => $this->version, 'rollback_trigger' => $this->when($technical, $this->rollback_trigger), 'rollback_steps' => $this->when($technical, $this->rollback_steps), 'data_recovery_steps' => $this->when($technical, $this->data_recovery_steps), 'estimated_rollback_minutes' => $this->estimated_rollback_minutes, 'validation_after_rollback' => $this->when($technical, $this->validation_after_rollback), 'responsible_user' => $this->whenLoaded('responsibleUser', fn () => ['id' => $this->responsibleUser->id, 'name' => $this->responsibleUser->name]), 'status' => $this->status, 'lock_version' => $this->lock_version];
    }
}
