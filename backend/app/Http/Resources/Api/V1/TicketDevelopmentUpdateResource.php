<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketDevelopmentUpdateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'creator' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $this->creator->name]), 'progress_percentage' => $this->progress_percentage, 'summary' => $this->summary, 'completed_items' => $this->completed_items ?? [], 'remaining_items' => $this->remaining_items ?? [], 'blockers' => $this->blockers ?? [], 'next_steps' => $this->next_steps ?? [], 'is_internal' => $this->is_internal, 'created_at' => $this->created_at?->toISOString()];
    }
}
