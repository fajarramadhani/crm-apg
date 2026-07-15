<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HolidayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'working_calendar_id' => $this->working_calendar_id, 'working_calendar' => $this->whenLoaded('workingCalendar', fn () => ['id' => $this->workingCalendar->id, 'code' => $this->workingCalendar->code, 'name' => $this->workingCalendar->name]), 'date' => $this->date?->format('Y-m-d'), 'name' => $this->name, 'is_recurring' => $this->is_recurring];
    }
}
