<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketSlaAlertResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket' => [
                'id' => $this->ticket_id,
                'number' => $this->ticket->ticket_number,
                'priority' => $this->ticket->priority,
                'status' => $this->ticket->status,
                'application_name' => $this->ticket->application?->name,
                'requester_name' => $this->ticket->requester?->name,
                'pic_name' => $this->ticket->pic?->name,
            ],
            'sla_type' => $this->sla_type,
            'alert_level' => $this->alert_level,
            'threshold_percent' => $this->threshold_percent,
            'elapsed_minutes' => $this->elapsed_minutes,
            'target_minutes' => $this->target_minutes,
            'remaining_minutes' => $this->remaining_minutes,
            'triggered_at' => $this->triggered_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'action_url' => "/tickets/{$this->ticket_id}",
        ];
    }
}
