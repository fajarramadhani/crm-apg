<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketSolutionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! ($request->user()?->hasPermission('ticket.solution_plan.view') ?? false)) {
            return ['status' => $this->status, 'submitted_at' => $this->submitted_at?->toISOString(), 'approved_at' => $this->status === 'approved' ? $this->reviewed_at?->toISOString() : null];
        }

        return [
            'id' => $this->id,
            'version' => $this->version,
            'lock_version' => $this->lock_version,
            'is_current' => $this->is_current,
            'created_by' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $this->creator->name]),
            'solution_summary' => $this->solution_summary,
            'implementation_steps' => $this->implementation_steps,
            'affected_components' => $this->affected_components ?? [],
            'dependencies' => $this->dependencies ?? [],
            'estimated_effort_minutes' => $this->estimated_effort_minutes,
            'risk_level' => $this->risk_level,
            'risk_description' => $this->risk_description,
            'rollback_plan' => $this->rollback_plan,
            'testing_plan' => $this->testing_plan,
            'deployment_consideration' => $this->deployment_consideration,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'reviewed_by' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? ['id' => $this->reviewer->id, 'name' => $this->reviewer->name] : null),
            'review_notes' => $this->review_notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
