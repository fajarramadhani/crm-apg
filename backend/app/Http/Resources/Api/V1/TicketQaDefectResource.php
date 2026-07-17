<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketQaDefectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'qa_test_run_id' => $this->qa_test_run_id,
            'qa_test_case_id' => $this->qa_test_case_id,
            'reported_by' => $this->reporter ? ['id' => $this->reporter->id, 'name' => $this->reporter->name] : null,
            'assigned_to' => $this->assignee ? ['id' => $this->assignee->id, 'name' => $this->assignee->name] : null,
            'defect_number' => $this->defect_number,
            'title' => $this->title,
            'description' => $this->description,
            'severity' => $this->severity,
            'priority' => $this->priority,
            'steps_to_reproduce' => $this->steps_to_reproduce,
            'expected_result' => $this->expected_result,
            'actual_result' => $this->actual_result,
            'environment' => $this->environment,
            'status' => $this->status,
            'resolved_by' => $this->resolver ? ['id' => $this->resolver->id, 'name' => $this->resolver->name] : null,
            'resolved_at' => $this->resolved_at?->toISOString(),
            'resolution_notes' => $this->resolution_notes,
            'verified_by' => $this->verifier ? ['id' => $this->verifier->id, 'name' => $this->verifier->name] : null,
            'verified_at' => $this->verified_at?->toISOString(),
            'comments' => TicketCommentResource::collection($this->whenLoaded('comments')),
            'attachments' => TicketAttachmentResource::collection($this->whenLoaded('attachments')),
            'histories' => TicketQaDefectHistoryResource::collection($this->whenLoaded('histories')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
