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
        $safeAttachments = $this->relationLoaded('attachments')
            ? ($technical ? $this->attachments : $this->attachments->filter(fn ($attachment) => $attachment->defect_id === null && $attachment->uat_finding_id === null && (! in_array($attachment->category, ['development_evidence', 'test_evidence', 'log', 'documentation', 'qa_evidence', 'defect_evidence', 'retest_evidence', 'uat_evidence', 'uat_finding_evidence', 'uat_retest_evidence', 'uat_signoff_document'], true) || ($attachment->visibility ?? 'internal') === 'requester')))
            : null;

        $defectCount = $this->relationLoaded('qaDefects') ? $this->qaDefects->count() : $this->qaDefects()->count();
        $defectOpenCount = $this->relationLoaded('qaDefects') ? $this->qaDefects->whereIn('status', ['open', 'in_progress', 'reopened'])->count() : $this->qaDefects()->whereIn('status', ['open', 'in_progress', 'reopened'])->count();

        $uatFindingCount = $this->relationLoaded('uatFindings') ? $this->uatFindings->count() : $this->uatFindings()->count();
        $uatFindingOpenCount = $this->relationLoaded('uatFindings') ? $this->uatFindings->whereIn('status', ['open', 'in_progress', 'reopened'])->count() : $this->uatFindings()->whereIn('status', ['open', 'in_progress', 'reopened'])->count();

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
            'development_started_at' => $this->development_started_at?->toISOString(), 'development_completed_at' => $this->development_completed_at?->toISOString(),
            'internal_testing_started_at' => $this->internal_testing_started_at?->toISOString(), 'internal_testing_completed_at' => $this->internal_testing_completed_at?->toISOString(), 'ready_for_qa_at' => $this->ready_for_qa_at?->toISOString(),
            'progress_percentage' => (int) ($this->progress_percentage ?? 0), 'latest_progress_at' => $this->latest_progress_at?->toISOString(),
            'internal_testing_status' => match ($this->status) {
                TicketStatus::InternalTesting => 'in_progress', TicketStatus::ReadyForQa => 'passed', default => null
            },
            'analysis_summary' => ['status' => $this->analysis_completed_at ? 'completed' : ($this->analysis_started_at ? 'in_progress' : 'not_started'), 'completed_at' => $this->analysis_completed_at?->toISOString()],
            'solution_plan_summary' => ['status' => in_array($this->status, [TicketStatus::ReadyForDevelopment, TicketStatus::DevelopmentInProgress, TicketStatus::InternalTesting, TicketStatus::ReadyForQa, TicketStatus::QaAssignment, TicketStatus::QaInProgress, TicketStatus::QaFailed, TicketStatus::QaRetest, TicketStatus::ReadyForUat, TicketStatus::UatAssignment, TicketStatus::UatInProgress, TicketStatus::UatFailed, TicketStatus::UatRetest, TicketStatus::UatApproved], true) ? 'approved' : ($this->status === TicketStatus::PlanReview ? 'submitted' : ($this->current_solution_plan_id ? 'draft' : 'not_started')), 'submitted_at' => $this->plan_submitted_at?->toISOString(), 'approved_at' => $this->plan_approved_at?->toISOString()],
            'submitted_at' => $this->submitted_at?->toISOString(), 'validated_at' => $this->validated_at?->toISOString(), 'rejected_at' => $this->rejected_at?->toISOString(), 'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString(),
            'allowed_actions' => $this->allowedActions($request, $own),
            'attachments' => $safeAttachments === null ? [] : TicketAttachmentResource::collection($safeAttachments)->resolve($request),
            'comments' => TicketCommentResource::collection($this->whenLoaded('comments', fn () => $technical ? $this->comments : $this->comments->whereNull('defect_id')->whereNull('uat_finding_id'))),
            'history' => TicketStatusHistoryResource::collection($this->whenLoaded('histories')),
            'assignment_notes' => $this->when($technical && ! $own && $this->relationLoaded('assignments'), fn () => $this->assignments->where('is_current', true)->first()?->notes),
            'solution_plan_preview' => $this->when($technical && ! $own && $this->relationLoaded('currentSolutionPlan'), fn () => $this->currentSolutionPlan ? ['version' => $this->currentSolutionPlan->version, 'estimated_effort_minutes' => $this->currentSolutionPlan->estimated_effort_minutes, 'risk_level' => $this->currentSolutionPlan->risk_level, 'submitted_at' => $this->currentSolutionPlan->submitted_at?->toISOString()] : null),
            'actual_work_minutes' => $this->when($technical && ! $own, fn () => isset($this->actual_work_minutes) ? (int) $this->actual_work_minutes : ($this->relationLoaded('worklogs') ? (int) $this->worklogs->sum('minutes_spent') : 0)),
            'latest_development_update' => $this->when($technical && ! $own && $this->relationLoaded('developmentUpdates'), fn () => ($latest = $this->developmentUpdates->first()) ? ['progress_percentage' => $latest->progress_percentage, 'summary' => $latest->summary, 'blockers' => $latest->blockers ?? [], 'created_at' => $latest->created_at?->toISOString()] : null),

            // QA Phase 9 fields
            'qa_assignee' => $this->whenLoaded('qaAssignee', fn () => $this->qaAssignee ? ['id' => $this->qaAssignee->id, 'name' => $this->qaAssignee->name] : null),
            'qa_assigned_by' => $this->when($technical && ! $own && $this->relationLoaded('qaAssignedBy'), fn () => $this->qaAssignedBy ? ['id' => $this->qaAssignedBy->id, 'name' => $this->qaAssignedBy->name] : null),
            'qa_assigned_at' => $this->when($technical && ! $own, $this->qa_assigned_at?->toISOString()),
            'qa_started_at' => $this->when($technical && ! $own, $this->qa_started_at?->toISOString()),
            'qa_completed_at' => $this->when($technical && ! $own, $this->qa_completed_at?->toISOString()),
            'qa_cycle_number' => (int) $this->qa_cycle_number,
            'latest_qa_result' => $this->latest_qa_result,
            'ready_for_uat_at' => $this->ready_for_uat_at?->toISOString(),
            'defect_count' => (int) $defectCount,
            'defect_open_count' => (int) $defectOpenCount,

            // UAT Phase 10 fields
            'uat_assignee' => $this->whenLoaded('uatAssignee', fn () => $this->uatAssignee ? ['id' => $this->uatAssignee->id, 'name' => $this->uatAssignee->name] : null),
            'uat_assigned_by' => $this->when($technical && ! $own && $this->relationLoaded('uatAssignedBy'), fn () => $this->uatAssignedBy ? ['id' => $this->uatAssignedBy->id, 'name' => $this->uatAssignedBy->name] : null),
            'uat_assigned_at' => $this->when($technical && ! $own, $this->uat_assigned_at?->toISOString()),
            'uat_started_at' => $this->uat_started_at?->toISOString(),
            'uat_completed_at' => $this->uat_completed_at?->toISOString(),
            'uat_cycle_number' => (int) $this->uat_cycle_number,
            'latest_uat_result' => $this->latest_uat_result,
            'uat_approved_at' => $this->uat_approved_at?->toISOString(),
            'uat_finding_count' => (int) $uatFindingCount,
            'uat_finding_open_count' => (int) $uatFindingOpenCount,
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
        if ($request->user()?->hasPermission('ticket.development.start') && $this->status === TicketStatus::ReadyForDevelopment && $this->current_assignee_id === $request->user()?->id) {
            return ['start_development'];
        }
        if ($request->user()?->hasPermission('ticket.development.update') && $this->status === TicketStatus::DevelopmentInProgress && $this->current_assignee_id === $request->user()?->id) {
            $hasDefects = $this->qaDefects()->whereIn('status', ['open', 'in_progress', 'reopened'])->exists();
            $hasUatFindings = $this->uatFindings()->whereIn('status', ['open', 'in_progress', 'reopened'])->exists();

            $actions = ['add_worklog', 'update_progress', 'upload_evidence', 'manage_test_cases', ...($this->progress_percentage === 100 ? ['start_internal_testing'] : [])];

            if ($hasDefects) {
                $actions = ['start_rework_defect', 'resolve_defect'];

                // Retest conditions checking
                $lastFailure = $this->histories()->where('to_status', 'qa_failed')->latest()->first();
                if ($lastFailure) {
                    $hasReworkWorklog = $this->worklogs()->where('created_at', '>=', $lastFailure->created_at)->where('activity_type', 'rework')->exists();
                    $hasPassedInternal = $this->internalTestRuns()->where('status', 'passed')->where('completed_at', '>=', $lastFailure->created_at)->exists();
                    $hasNoActiveInternal = ! $this->internalTestRuns()->where('status', 'in_progress')->exists();

                    if ($hasReworkWorklog && $hasPassedInternal && $hasNoActiveInternal && $this->progress_percentage === 100) {
                        $actions[] = 'submit_qa_retest';
                    }
                }
            }

            if ($hasUatFindings) {
                $actions = ['start_uat_rework', 'resolve_uat_finding'];

                // UAT Retest conditions checking
                $lastUatFailure = $this->histories()->where('to_status', 'uat_failed')->latest()->first();
                if ($lastUatFailure) {
                    $hasReworkWorklog = $this->worklogs()->where('created_at', '>=', $lastUatFailure->created_at)->where('activity_type', 'rework')->exists();
                    $hasPassedInternal = $this->internalTestRuns()->where('status', 'passed')->where('completed_at', '>=', $lastUatFailure->created_at)->exists();
                    $hasNoActiveInternal = ! $this->internalTestRuns()->where('status', 'in_progress')->exists();

                    if ($hasReworkWorklog && $hasPassedInternal && $hasNoActiveInternal && $this->progress_percentage === 100) {
                        $actions[] = 'submit_uat_retest';
                    }
                }
            }

            return $actions;
        }
        if ($request->user()?->hasPermission('ticket.internal_test_run.manage') && $this->status === TicketStatus::InternalTesting && $this->current_assignee_id === $request->user()?->id) {
            return ['record_test_result', 'complete_internal_testing'];
        }

        // QA actions
        if ($request->user()?->hasPermission('ticket.qa.assign') && $this->status === TicketStatus::ReadyForQa && $this->qa_assignee_id === null) {
            return ['assign_qa'];
        }
        if ($request->user()?->hasPermission('ticket.qa.start') && $this->qa_assignee_id === $request->user()?->id) {
            if ($this->status === TicketStatus::QaAssignment || $this->status === TicketStatus::QaRetest) {
                return ['start_qa'];
            }
        }
        if ($request->user()?->hasPermission('ticket.qa_test_run.manage') && $this->status === TicketStatus::QaInProgress && $this->qa_assignee_id === $request->user()?->id) {
            return ['manage_qa', 'start_qa_run', 'record_qa_result', 'complete_qa_run', 'manage_test_cases', 'create_defect'];
        }

        // UAT actions
        if ($request->user()?->hasPermission('ticket.uat.assign') && $this->status === TicketStatus::ReadyForUat && $this->uat_assignee_id === null) {
            return ['assign_uat'];
        }
        if ($request->user()?->hasPermission('ticket.uat.start') && $this->uat_assignee_id === $request->user()?->id) {
            if ($this->status === TicketStatus::UatAssignment || $this->status === TicketStatus::UatRetest) {
                return ['start_uat'];
            }
        }
        if ($request->user()?->hasPermission('ticket.uat_run.manage') && $this->status === TicketStatus::UatInProgress && $this->uat_assignee_id === $request->user()?->id) {
            return ['manage_uat', 'start_uat_run', 'record_uat_result', 'complete_uat_run', 'create_uat_finding'];
        }

        if (! $own) {
            return $this->status === TicketStatus::PendingValidation ? ['validate', 'request_revision', 'reject', 'transfer'] : [];
        }

        return match ($this->status) {
            TicketStatus::NeedRevision => ['update', 'resubmit', 'cancel', 'manage_attachment'], TicketStatus::Draft => ['update', 'submit', 'cancel', 'manage_attachment'], TicketStatus::PendingValidation => ['cancel', 'manage_attachment'], default => []
        };
    }
}
