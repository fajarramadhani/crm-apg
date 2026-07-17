<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketUatRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'requester' => $this->requester ? ['id' => $this->requester->id, 'name' => $this->requester->name] : null,
            'cycle_number' => (int) $this->cycle_number,
            'run_number' => (int) $this->run_number,
            'environment' => $this->environment,
            'build_reference' => $this->build_reference,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'status' => $this->status,
            'summary' => $this->summary,
            'results' => TicketUatResultResource::collection($this->whenLoaded('results')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
