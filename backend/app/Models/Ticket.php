<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['ticket_number', 'requester_id', 'requester_name', 'requester_email', 'requester_phone', 'submission_source', 'division_id', 'branch_id', 'office_id', 'request_category', 'application_id', 'application_module_id', 'ticket_category_id', 'requested_priority_id', 'final_priority_id', 'sla_policy_id', 'working_calendar_id', 'current_assignee_id', 'assigned_by', 'release_owner_id', 'title', 'description', 'business_impact', 'urgency', 'incident_occurred_at', 'affected_url', 'reference', 'expected_result', 'actual_result', 'reproduction_steps', 'request_purpose', 'target_needed_at', 'change_reason', 'expected_impact', 'recurring_indication', 'status', 'current_division_id', 'submitted_at', 'validated_at', 'rejected_at', 'triage_started_at', 'assigned_at', 'response_due_at', 'resolution_due_at', 'sla_timezone', 'analysis_started_at', 'analysis_completed_at', 'plan_submitted_at', 'plan_approved_at', 'current_analysis_id', 'current_solution_plan_id', 'development_started_at', 'development_completed_at', 'internal_testing_started_at', 'internal_testing_completed_at', 'ready_for_qa_at', 'progress_percentage', 'latest_progress_at', 'qa_assignee_id', 'qa_assigned_by', 'qa_assigned_at', 'qa_started_at', 'qa_completed_at', 'qa_cycle_number', 'latest_qa_result', 'uat_assignee_id', 'uat_assigned_by', 'uat_assigned_at', 'uat_started_at', 'uat_completed_at', 'uat_cycle_number', 'latest_uat_result', 'uat_approved_at', 'approval_requested_at', 'approval_completed_at', 'approved_for_release_at', 'release_preparation_started_at', 'release_ready_at', 'deployment_cycle_number', 'deployment_scheduled_at', 'deployment_started_at', 'deployed_at', 'monitoring_started_at', 'monitoring_completed_at', 'requester_confirmation_requested_at', 'requester_confirmed_at', 'closed_at', 'closed_by', 'latest_deployment_result', 'current_deployment_id', 'current_monitoring_session_id', 'post_release_status', 'workflow_id', 'workflow_version', 'workflow_snapshot', 'workflow_mode', 'current_workflow_stage', 'workflow_previous_stage', 'workflow_stage_entered_at'])]
class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['status' => TicketStatus::class, 'incident_occurred_at' => 'datetime', 'target_needed_at' => 'datetime', 'submitted_at' => 'datetime', 'validated_at' => 'datetime', 'rejected_at' => 'datetime', 'triage_started_at' => 'datetime', 'assigned_at' => 'datetime', 'response_due_at' => 'datetime', 'resolution_due_at' => 'datetime', 'analysis_started_at' => 'datetime', 'analysis_completed_at' => 'datetime', 'plan_submitted_at' => 'datetime', 'plan_approved_at' => 'datetime', 'development_started_at' => 'datetime', 'development_completed_at' => 'datetime', 'internal_testing_started_at' => 'datetime', 'internal_testing_completed_at' => 'datetime', 'ready_for_qa_at' => 'datetime', 'latest_progress_at' => 'datetime', 'closed_at' => 'datetime', 'qa_assigned_at' => 'datetime', 'qa_started_at' => 'datetime', 'qa_completed_at' => 'datetime', 'ready_for_uat_at' => 'datetime', 'uat_assigned_at' => 'datetime', 'uat_started_at' => 'datetime', 'uat_completed_at' => 'datetime', 'uat_approved_at' => 'datetime', 'approval_requested_at' => 'datetime', 'approval_completed_at' => 'datetime', 'approved_for_release_at' => 'datetime', 'release_preparation_started_at' => 'datetime', 'release_ready_at' => 'datetime', 'deployment_scheduled_at' => 'datetime', 'deployment_started_at' => 'datetime', 'deployed_at' => 'datetime', 'monitoring_started_at' => 'datetime', 'monitoring_completed_at' => 'datetime', 'requester_confirmation_requested_at' => 'datetime', 'requester_confirmed_at' => 'datetime', 'workflow_snapshot' => 'array', 'workflow_version' => 'integer', 'workflow_stage_entered_at' => 'datetime'];
    }

    public function scopeForSummary(Builder $query): Builder
    {
        $columns = array_values(array_diff($this->getFillable(), ['workflow_snapshot']));

        return $query
            ->select(array_map(fn (string $column): string => "tickets.{$column}", [
                'id',
                ...$columns,
                'release_owner_id',
                'created_at',
                'updated_at',
                'deleted_at',
            ]))
            ->withCount([
                'qaDefects as defect_count',
                'qaDefects as defect_open_count' => fn (Builder $query) => $query->whereIn('status', ['open', 'in_progress', 'reopened']),
                'uatFindings as uat_finding_count',
                'uatFindings as uat_finding_open_count' => fn (Builder $query) => $query->whereIn('status', ['open', 'in_progress', 'reopened']),
            ]);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_id');
    }

    public function assignmentHistories(): HasMany
    {
        return $this->hasMany(TicketAssignmentHistory::class, 'ticket_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function currentDivision(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'current_division_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function applicationModule(): BelongsTo
    {
        return $this->belongsTo(ApplicationModule::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function requestedPriority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'requested_priority_id');
    }

    public function finalPriority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'final_priority_id');
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    public function workingCalendar(): BelongsTo
    {
        return $this->belongsTo(WorkingCalendar::class);
    }

    public function currentAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_assignee_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class);
    }

    public function publicHandlingAssignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class)
            ->whereIn('assignment_type', ['primary', 'secondary'])
            ->where('is_current', true)
            ->whereNull('ended_at')
            ->orderByRaw("case when assignment_type = 'primary' then 0 else 1 end")
            ->orderByDesc('id');
    }

    public function hasActivePrimaryPic(User $user): bool
    {
        return $this->assignments()
            ->where('assigned_to', $user->id)
            ->where('assignment_type', 'primary')
            ->where('is_current', true)
            ->exists();
    }

    public function scopeAssignedToUser($query, User $user)
    {
        return $query->where(function ($q) use ($user): void {
            $q->where('current_assignee_id', $user->id)
                ->orWhereHas('assignments', function ($q2) use ($user): void {
                    $q2->where('assigned_to', $user->id)->where('is_current', true);
                });
        });
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class)->oldest('created_at')->oldest('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->oldest();
    }

    public function publicTrackingTokens(): HasMany
    {
        return $this->hasMany(PublicTicketTrackingToken::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(TicketAnalysis::class)->orderByDesc('version');
    }

    public function currentAnalysis(): HasOne
    {
        return $this->hasOne(TicketAnalysis::class, 'id', 'current_analysis_id');
    }

    public function solutionPlans(): HasMany
    {
        return $this->hasMany(TicketSolutionPlan::class)->orderByDesc('version');
    }

    public function currentSolutionPlan(): HasOne
    {
        return $this->hasOne(TicketSolutionPlan::class, 'id', 'current_solution_plan_id');
    }

    public function worklogs(): HasMany
    {
        return $this->hasMany(TicketWorklog::class)->latest('work_date')->latest('id');
    }

    public function developmentUpdates(): HasMany
    {
        return $this->hasMany(TicketDevelopmentUpdate::class)->latest();
    }

    public function internalTestCases(): HasMany
    {
        return $this->hasMany(TicketInternalTestCase::class)->orderBy('case_number');
    }

    public function internalTestRuns(): HasMany
    {
        return $this->hasMany(TicketInternalTestRun::class)->latest('run_number');
    }

    public function qaAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qa_assignee_id');
    }

    public function qaAssignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qa_assigned_by');
    }

    public function qaAssignments(): HasMany
    {
        return $this->hasMany(TicketQaAssignment::class);
    }

    public function qaTestCases(): HasMany
    {
        return $this->hasMany(TicketQaTestCase::class)->orderBy('case_number');
    }

    public function qaTestRuns(): HasMany
    {
        return $this->hasMany(TicketQaTestRun::class)->latest('run_number');
    }

    public function qaDefects(): HasMany
    {
        return $this->hasMany(TicketQaDefect::class);
    }

    public function uatAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uat_assignee_id');
    }

    public function uatAssignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uat_assigned_by');
    }

    public function uatAssignments(): HasMany
    {
        return $this->hasMany(TicketUatAssignment::class);
    }

    public function uatScenarios(): HasMany
    {
        return $this->hasMany(TicketUatScenario::class)->orderBy('scenario_number');
    }

    public function uatRuns(): HasMany
    {
        return $this->hasMany(TicketUatRun::class)->latest('run_number');
    }

    public function uatFindings(): HasMany
    {
        return $this->hasMany(TicketUatFinding::class);
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(TicketApprovalRequest::class)->latest('cycle_number');
    }

    public function releasePlans(): HasMany
    {
        return $this->hasMany(TicketReleasePlan::class)->latest('version');
    }

    public function rollbackPlans(): HasMany
    {
        return $this->hasMany(TicketRollbackPlan::class)->latest('version');
    }

    public function releaseChecklistItems(): HasMany
    {
        return $this->hasMany(TicketReleaseChecklistItem::class);
    }

    public function releaseOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'release_owner_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(TicketDeployment::class)->orderByDesc('deployment_number');
    }

    public function currentDeployment(): HasOne
    {
        return $this->hasOne(TicketDeployment::class, 'id', 'current_deployment_id');
    }

    public function rollbackExecutions(): HasMany
    {
        return $this->hasMany(TicketRollbackExecution::class)->orderByDesc('rollback_number');
    }

    public function monitoringSessions(): HasMany
    {
        return $this->hasMany(TicketMonitoringSession::class)->orderByDesc('cycle_number');
    }

    public function currentMonitoringSession(): HasOne
    {
        return $this->hasOne(TicketMonitoringSession::class, 'id', 'current_monitoring_session_id');
    }

    public function postReleaseIncidents(): HasMany
    {
        return $this->hasMany(TicketPostReleaseIncident::class)->orderByDesc('incident_number');
    }

    public function requesterConfirmations(): HasMany
    {
        return $this->hasMany(TicketRequesterConfirmation::class)->latest();
    }

    public function closures(): HasMany
    {
        return $this->hasMany(TicketClosure::class)->latest();
    }

    public function kbArticles(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeBaseArticle::class, 'knowledge_base_article_ticket', 'ticket_id', 'article_id')
            ->withPivot(['relation_type', 'linked_by', 'created_at']);
    }
}
