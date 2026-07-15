<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SlaPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'priority_id' => $this->priority_id, 'priority' => $this->whenLoaded('priority', fn () => ['id' => $this->priority->id, 'key' => $this->priority->key, 'name' => $this->priority->name, 'level' => $this->priority->level]), 'response_minutes' => $this->response_minutes, 'resolution_minutes' => $this->resolution_minutes, 'working_calendar_id' => $this->working_calendar_id, 'working_calendar' => $this->whenLoaded('workingCalendar', fn () => ['id' => $this->workingCalendar->id, 'code' => $this->workingCalendar->code, 'name' => $this->workingCalendar->name, 'timezone' => $this->workingCalendar->timezone]), 'is_active' => $this->is_active];
    }
}
