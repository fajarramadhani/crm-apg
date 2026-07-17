<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketUatResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uat_run_id' => $this->uat_run_id,
            'uat_scenario_id' => $this->uat_scenario_id,
            'executor' => $this->executor ? ['id' => $this->executor->id, 'name' => $this->executor->name] : null,
            'status' => $this->status,
            'actual_result' => $this->actual_result,
            'notes' => $this->notes,
            'executed_at' => $this->executed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
