<?php

namespace App\Http\Resources\Api\V1;

use App\Services\PublicTicketStatusMapper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicRequestHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ticket_number' => $this->ticket_number,
            'title' => $this->title,
            'branch' => $this->branch?->name,
            'category' => $this->category?->name,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'status' => app(PublicTicketStatusMapper::class)->map($this->current_workflow_stage ?: $this->status),
            'last_updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
