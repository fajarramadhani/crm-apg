<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketInternalTestResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'test_case_id' => $this->test_case_id, 'status' => $this->status, 'actual_result' => $this->actual_result, 'notes' => $this->notes, 'executed_at' => $this->executed_at?->toISOString()];
    }
}
