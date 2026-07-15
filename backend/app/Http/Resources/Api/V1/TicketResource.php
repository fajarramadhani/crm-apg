<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\TicketStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $own = $request->user()?->id === $this->requester_id;
        $technical = $request->user()?->hasPermission('ticket.technical.view') ?? false;

        return [
            'id' => $this->id, 'ticket_number' => $this->ticket_number, 'title' => $this->title, 'description' => $this->description,
            'business_impact' => $this->business_impact, 'urgency' => $this->urgency, 'incident_occurred_at' => $this->incident_occurred_at?->toISOString(), 'affected_url' => $this->affected_url,
            'expected_result' => $this->expected_result, 'actual_result' => $this->actual_result, 'reproduction_steps' => $this->reproduction_steps, 'request_purpose' => $this->request_purpose,
            'target_needed_at' => $this->target_needed_at?->toISOString(), 'change_reason' => $this->change_reason, 'expected_impact' => $this->expected_impact, 'recurring_indication' => $this->recurring_indication,
            'status' => $this->status->value,
            'requester' => $this->whenLoaded('requester', fn () => ['id' => $this->requester->id, 'name' => $this->requester->name]),
            'division' => $this->whenLoaded('division', fn () => ['id' => $this->division->id, 'code' => $this->division->code, 'name' => $this->division->name]),
            'current_division' => $this->whenLoaded('currentDivision', fn () => ['id' => $this->currentDivision->id, 'code' => $this->currentDivision->code, 'name' => $this->currentDivision->name]),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? ['id' => $this->branch->id, 'code' => $this->branch->code, 'name' => $this->branch->name] : null),
            'application' => $this->whenLoaded('application', fn () => $this->application ? ['id' => $this->application->id, 'code' => $this->application->code, 'name' => $this->application->name] : null),
            'application_module' => $this->whenLoaded('applicationModule', fn () => $this->applicationModule ? ['id' => $this->applicationModule->id, 'code' => $this->applicationModule->code, 'name' => $this->applicationModule->name] : null),
            'category' => $this->whenLoaded('category', fn () => ['id' => $this->category->id, 'code' => $this->category->code, 'name' => $this->category->name, 'type' => $this->category->type]),
            'requested_priority' => $this->whenLoaded('requestedPriority', fn () => $this->requestedPriority ? ['id' => $this->requestedPriority->id, 'key' => $this->requestedPriority->key, 'name' => $this->requestedPriority->name] : null),
            'final_priority' => $this->whenLoaded('finalPriority', fn () => $this->finalPriority ? ['id' => $this->finalPriority->id, 'key' => $this->finalPriority->key, 'name' => $this->finalPriority->name] : null),
            'sla_policy' => $this->whenLoaded('slaPolicy', fn () => $this->slaPolicy ? ['id' => $this->slaPolicy->id, 'response_minutes' => $this->slaPolicy->response_minutes, 'resolution_minutes' => $this->slaPolicy->resolution_minutes] : null),
            'assignee' => $this->whenLoaded('currentAssignee', fn () => $this->currentAssignee ? ['id' => $this->currentAssignee->id, 'name' => $this->currentAssignee->name] : null),
            'response_due_at' => $this->response_due_at?->toISOString(), 'resolution_due_at' => $this->resolution_due_at?->toISOString(), 'sla_timezone' => $this->sla_timezone,
            'triage_started_at' => $this->triage_started_at?->toISOString(), 'assigned_at' => $this->assigned_at?->toISOString(),
            'analysis_started_at' => $this->analysis_started_at?->toISOString(), 'analysis_completed_at' => $this->analysis_completed_at?->toISOString(), 'plan_submitted_at' => $this->plan_submitted_at?->toISOString(), 'plan_approved_at' => $this->plan_approved_at?->toISOString(),
            'analysis_summary' => ['status' => $this->analysis_completed_at ? 'completed' : ($this->analysis_started_at ? 'in_progress' : 'not_started'), 'completed_at' => $this->analysis_completed_at?->toISOString()],
            'solution_plan_summary' => ['status' => $this->status === TicketStatus::ReadyForDevelopment ? 'approved' : ($this->status === TicketStatus::PlanReview ? 'submitted' : ($this->current_solution_plan_id ? 'draft' : 'not_started')), 'submitted_at' => $this->plan_submitted_at?->toISOString(), 'approved_at' => $this->plan_approved_at?->toISOString()],
            'submitted_at' => $this->submitted_at?->toISOString(), 'validated_at' => $this->validated_at?->toISOString(), 'rejected_at' => $this->rejected_at?->toISOString(), 'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString(),
            'allowed_actions' => $this->allowedActions($request, $own),
            'attachments' => TicketAttachmentResource::collection($this->whenLoaded('attachments')),
            'comments' => TicketCommentResource::collection($this->whenLoaded('comments')),
            'history' => TicketStatusHistoryResource::collection($this->whenLoaded('histories')),
            'assignment_notes' => $this->when($technical && ! $own && $this->relationLoaded('assignments'), fn () => $this->assignments->where('is_current', true)->first()?->notes),
            'solution_plan_preview' => $this->when($technical && ! $own && $this->relationLoaded('currentSolutionPlan'), fn () => $this->currentSolutionPlan ? ['version' => $this->currentSolutionPlan->version, 'estimated_effort_minutes' => $this->currentSolutionPlan->estimated_effort_minutes, 'risk_level' => $this->currentSolutionPlan->risk_level, 'submitted_at' => $this->currentSolutionPlan->submitted_at?->toISOString()] : null),
        ];
    }

    private function allowedActions(Request $request, bool $own): array
    {
        if ($request->user()?->hasPermission('ticket.triage.start') && $this->status === TicketStatus::Validated) {
            return ['start_triage'];
        }
        if ($request->user()?->hasPermission('ticket.assign') && $this->status === TicketStatus::Triage) {
            return ['assign'];
        }
        if ($request->user()?->hasPermission('ticket.analysis.start') && $this->status === TicketStatus::Assigned && $this->current_assignee_id === $request->user()?->id) {
            return ['start_analysis'];
        }
        if (! $own) {
            return $this->status === TicketStatus::PendingValidation ? ['validate', 'request_revision', 'reject', 'transfer'] : [];
        }

        return match ($this->status) {
            TicketStatus::NeedRevision => ['update', 'resubmit', 'cancel', 'manage_attachment'], TicketStatus::Draft => ['update', 'submit', 'cancel', 'manage_attachment'], TicketStatus::PendingValidation => ['cancel', 'manage_attachment'], default => []
        };
    }
}
