<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketQaTestCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'case_number' => $this->case_number,
            'title' => $this->title,
            'test_type' => $this->test_type,
            'preconditions' => $this->preconditions,
            'steps' => $this->steps,
            'expected_result' => $this->expected_result,
            'priority' => $this->priority,
            'is_regression' => (bool) $this->is_regression,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
