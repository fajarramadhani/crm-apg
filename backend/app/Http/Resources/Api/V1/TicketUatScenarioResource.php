<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketUatScenarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'creator' => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null,
            'scenario_number' => $this->scenario_number,
            'title' => $this->title,
            'business_objective' => $this->business_objective,
            'preconditions' => $this->preconditions,
            'steps' => $this->steps,
            'expected_result' => $this->expected_result,
            'acceptance_criteria' => $this->acceptance_criteria,
            'priority' => $this->priority,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
