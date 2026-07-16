<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketWorklogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]), 'work_date' => $this->work_date?->toDateString(), 'minutes_spent' => $this->minutes_spent, 'activity_type' => $this->activity_type, 'description' => $this->description, 'progress_before' => $this->progress_before, 'progress_after' => $this->progress_after, 'is_internal' => $this->is_internal, 'created_at' => $this->created_at?->toISOString()];
    }
}
