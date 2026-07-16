<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketInternalTestRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'run_number' => $this->run_number, 'started_at' => $this->started_at?->toISOString(), 'completed_at' => $this->completed_at?->toISOString(), 'status' => $this->status, 'environment' => $this->environment, 'build_reference' => $this->build_reference, 'summary' => $this->summary, 'results' => TicketInternalTestResultResource::collection($this->whenLoaded('results'))];
    }
}
