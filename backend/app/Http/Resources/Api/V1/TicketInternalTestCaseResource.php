<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketInternalTestCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'case_number' => $this->case_number, 'title' => $this->title, 'preconditions' => $this->preconditions, 'steps' => $this->steps, 'expected_result' => $this->expected_result, 'is_active' => $this->is_active, 'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString()];
    }
}
