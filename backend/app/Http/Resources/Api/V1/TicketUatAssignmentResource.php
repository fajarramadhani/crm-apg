<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketUatAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'requester' => $this->requester ? ['id' => $this->requester->id, 'name' => $this->requester->name] : null,
            'assigned_by' => $this->assigner ? ['id' => $this->assigner->id, 'name' => $this->assigner->name] : null,
            'assigned_at' => $this->assigned_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'is_current' => (bool) $this->is_current,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
