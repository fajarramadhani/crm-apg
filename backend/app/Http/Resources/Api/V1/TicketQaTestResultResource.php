<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketQaTestResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'qa_test_run_id' => $this->qa_test_run_id,
            'qa_test_case_id' => $this->qa_test_case_id,
            'executed_by' => $this->executor ? ['id' => $this->executor->id, 'name' => $this->executor->name] : null,
            'status' => $this->status,
            'actual_result' => $this->actual_result,
            'notes' => $this->notes,
            'executed_at' => $this->executed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
