<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketAnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! ($request->user()?->hasPermission('ticket.analysis.view') ?? false)) {
            return ['status' => $this->completed_at ? 'completed' : 'in_progress', 'completed_at' => $this->completed_at?->toISOString()];
        }

        return [
            'id' => $this->id,
            'version' => $this->version,
            'lock_version' => $this->lock_version,
            'is_current' => $this->is_current,
            'analyst' => $this->whenLoaded('analyst', fn () => ['id' => $this->analyst->id, 'name' => $this->analyst->name]),
            'problem_summary' => $this->problem_summary,
            'root_cause' => $this->root_cause,
            'technical_impact' => $this->technical_impact,
            'business_impact' => $this->business_impact,
            'affected_components' => $this->affected_components ?? [],
            'evidence' => $this->evidence,
            'assumptions' => $this->assumptions,
            'limitations' => $this->limitations,
            'analysis_started_at' => $this->analysis_started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
