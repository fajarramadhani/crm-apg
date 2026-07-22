<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SlaEscalationPolicyResource extends JsonResource
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
            'name' => $this->name,
            'priority' => $this->priority,
            'sla_type' => $this->sla_type,
            'warning_threshold_percent' => $this->warning_threshold_percent,
            'critical_threshold_percent' => $this->critical_threshold_percent,
            'inactivity_threshold_minutes' => $this->inactivity_threshold_minutes,
            'escalate_to_it_lead' => $this->escalate_to_it_lead,
            'escalate_to_manager' => $this->escalate_to_manager,
            'escalate_to_supervisor' => $this->escalate_to_supervisor,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
