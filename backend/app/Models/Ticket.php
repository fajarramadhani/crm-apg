<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['ticket_number', 'requester_id', 'division_id', 'branch_id', 'application_id', 'application_module_id', 'ticket_category_id', 'requested_priority_id', 'final_priority_id', 'sla_policy_id', 'working_calendar_id', 'current_assignee_id', 'assigned_by', 'title', 'description', 'business_impact', 'urgency', 'incident_occurred_at', 'affected_url', 'expected_result', 'actual_result', 'reproduction_steps', 'request_purpose', 'target_needed_at', 'change_reason', 'expected_impact', 'recurring_indication', 'status', 'current_division_id', 'submitted_at', 'validated_at', 'rejected_at', 'triage_started_at', 'assigned_at', 'response_due_at', 'resolution_due_at', 'sla_timezone', 'analysis_started_at', 'analysis_completed_at', 'plan_submitted_at', 'plan_approved_at', 'current_analysis_id', 'current_solution_plan_id', 'development_started_at', 'development_completed_at', 'internal_testing_started_at', 'internal_testing_completed_at', 'ready_for_qa_at', 'progress_percentage', 'latest_progress_at', 'qa_assignee_id', 'qa_assigned_by', 'qa_assigned_at', 'qa_started_at', 'qa_completed_at', 'qa_cycle_number', 'latest_qa_result', 'ready_for_uat_at'])]
class Ticket extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['status' => TicketStatus::class, 'incident_occurred_at' => 'datetime', 'target_needed_at' => 'datetime', 'submitted_at' => 'datetime', 'validated_at' => 'datetime', 'rejected_at' => 'datetime', 'triage_started_at' => 'datetime', 'assigned_at' => 'datetime', 'response_due_at' => 'datetime', 'resolution_due_at' => 'datetime', 'analysis_started_at' => 'datetime', 'analysis_completed_at' => 'datetime', 'plan_submitted_at' => 'datetime', 'plan_approved_at' => 'datetime', 'development_started_at' => 'datetime', 'development_completed_at' => 'datetime', 'internal_testing_started_at' => 'datetime', 'internal_testing_completed_at' => 'datetime', 'ready_for_qa_at' => 'datetime', 'latest_progress_at' => 'datetime', 'closed_at' => 'datetime', 'qa_assigned_at' => 'datetime', 'qa_started_at' => 'datetime', 'qa_completed_at' => 'datetime', 'ready_for_uat_at' => 'datetime'];
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

    public function histories(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class)->oldest('created_at')->oldest('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->oldest();
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
}
