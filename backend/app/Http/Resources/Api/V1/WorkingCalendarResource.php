<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkingCalendarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'name' => $this->name, 'timezone' => $this->timezone, 'workday_start' => substr((string) $this->workday_start, 0, 5), 'workday_end' => substr((string) $this->workday_end, 0, 5), 'working_days' => $this->working_days, 'is_active' => $this->is_active];
    }
}
