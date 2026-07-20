<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketReleasePlan;
use App\Models\TicketRollbackPlan;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalReleasePreparationTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $manager;

    private User $itLead;

    private User $pic;

    private User $otherManager;

    private Division $division;

    private Branch $branch;

    private TicketCategory $category;

    private TicketPriority $priority;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->division = Division::create(['code' => 'IT', 'name' => 'IT', 'is_active' => true]);
        $otherDivision = Division::create(['code' => 'OPS', 'name' => 'Operations', 'is_active' => true]);
        $this->branch = Branch::create(['code' => 'JKT', 'name' => 'Jakarta', 'is_active' => true]);
        $this->requester = $this->user('requester', 'requester11@local.test', $this->division);
        $this->manager = $this->user('manager', 'manager11@local.test', $this->division);
        $this->otherManager = $this->user('manager', 'manager-other11@local.test', $otherDivision);
        $this->itLead = $this->user('it_lead', 'lead11@local.test', $this->division);
        $this->pic = $this->user('pic', 'pic11@local.test', $this->division);
        $this->category = TicketCategory::create(['code' => 'CHANGE', 'name' => 'Change', 'type' => 'change', 'is_active' => true]);
        $this->priority = TicketPriority::create(['key' => 'high', 'name' => 'High', 'level' => 3, 'is_active' => true]);
    }

    public function test_it_lead_requests_release_approval_and_duplicate_is_rejected(): void
    {
        $ticket = $this->ticket();
        $response = $this->requestApproval($ticket)->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->assertSame(TicketStatus::ApprovalPending, $ticket->fresh()->status);
        $this->assertCount(2, $response->json('data.steps'));
        $this->assertDatabaseHas('ticket_approval_steps', ['approval_request_id' => $response->json('data.id'), 'step_type' => 'business_approval', 'approver_id' => $this->manager->id]);
        $this->assertDatabaseHas('ticket_approval_steps', ['approval_request_id' => $response->json('data.id'), 'step_type' => 'technical_readiness', 'approver_id' => $this->itLead->id]);
        $this->requestApproval($ticket)->assertConflict();
    }

    public function test_approval_request_requires_uat_approved_and_authorized_role(): void
    {
        $ticket = $this->ticket();
        $ticket->update(['status' => TicketStatus::UatInProgress]);
        $this->requestApproval($ticket)->assertConflict();
        $ticket->update(['status' => TicketStatus::UatApproved]);
        $this->actingAs($this->requester)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/request-release-approval", $this->approvalPayload())->assertForbidden();
    }

    public function test_only_assigned_manager_can_approve_and_duplicate_decision_conflicts(): void
    {
        $ticket = $this->ticket();
        $approval = $this->requestApproval($ticket)->json('data');
        $business = collect($approval['steps'])->firstWhere('step_type', 'business_approval');
        $this->actingAs($this->otherManager)->postJson("/api/v1/manager/tickets/{$ticket->id}/business-approval/approve", ['expected_version' => $business['version']])->assertForbidden();
        $this->actingAs($this->manager)->postJson("/api/v1/manager/tickets/{$ticket->id}/business-approval/approve", ['expected_version' => $business['version']])->assertOk();
        $this->actingAs($this->manager)->postJson("/api/v1/manager/tickets/{$ticket->id}/business-approval/approve", ['expected_version' => $business['version']])->assertConflict();
    }

    public function test_rejection_requires_reason_and_records_two_ticket_transitions(): void
    {
        $ticket = $this->ticket();
        $approval = $this->requestApproval($ticket)->json('data');
        $business = collect($approval['steps'])->firstWhere('step_type', 'business_approval');
        $url = "/api/v1/manager/tickets/{$ticket->id}/business-approval/reject";
        $this->actingAs($this->manager)->postJson($url, ['expected_version' => $business['version']])->assertUnprocessable();
        $this->actingAs($this->manager)->postJson($url, ['expected_version' => $business['version'], 'notes' => 'Business schedule is not acceptable.'])->assertOk();
        $this->assertSame(TicketStatus::DevelopmentInProgress, $ticket->fresh()->status);
        $this->assertLessThanOrEqual(90, $ticket->fresh()->progress_percentage);
        $this->assertDatabaseHas('ticket_status_histories', ['ticket_id' => $ticket->id, 'from_status' => 'approval_pending', 'to_status' => 'approval_revision']);
        $this->assertDatabaseHas('ticket_status_histories', ['ticket_id' => $ticket->id, 'from_status' => 'approval_revision', 'to_status' => 'development_in_progress']);
    }

    public function test_both_approved_steps_start_release_preparation(): void
    {
        $ticket = $this->ticket();
        $approval = $this->requestApproval($ticket)->json('data');
        $business = collect($approval['steps'])->firstWhere('step_type', 'business_approval');
        $technical = collect($approval['steps'])->firstWhere('step_type', 'technical_readiness');
        $this->actingAs($this->manager)->postJson("/api/v1/manager/tickets/{$ticket->id}/business-approval/approve", ['expected_version' => $business['version']])->assertOk();
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/technical-approval/approve", ['expected_version' => $technical['version']])->assertOk();
        $this->assertSame(TicketStatus::ReleasePreparation, $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_approval_requests', ['ticket_id' => $ticket->id, 'status' => 'approved']);
    }

    public function test_release_and_rollback_plan_validation_and_nested_ownership(): void
    {
        $ticket = $this->approvedTicket();
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/release-plan", [...$this->releasePayload(), 'affected_components' => []])->assertUnprocessable();
        $plan = $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/release-plan", $this->releasePayload())->assertCreated()->json('data');
        $this->assertSame(1, $plan['version']);
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/rollback-plan", ['rollback_trigger' => 'Failure threshold exceeded', 'rollback_steps' => [], 'estimated_rollback_minutes' => 30, 'validation_after_rollback' => ['Health check'], 'responsible_user_id' => $this->pic->id])->assertUnprocessable();
        $other = $this->approvedTicket('TIC-P11-OTHER');
        $this->actingAs($this->itLead)->putJson("/api/v1/it-lead/tickets/{$other->id}/release-plan/{$plan['id']}", [...$this->releasePayload(), 'expected_lock_version' => 1])->assertNotFound();
    }

    public function test_checklist_stale_and_blocked_items_prevent_readiness(): void
    {
        [$ticket, $plan, $rollback] = $this->preparedDocuments();
        $item = $ticket->releaseChecklistItems()->firstOrFail();
        $url = "/api/v1/it-lead/tickets/{$ticket->id}/release-checklist/{$item->id}/decision";
        $this->actingAs($this->itLead)->postJson($url, ['status' => 'blocked', 'notes' => 'Security review pending', 'expected_version' => 1])->assertOk();
        $this->actingAs($this->itLead)->postJson($url, ['status' => 'completed', 'expected_version' => 1])->assertConflict();
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/confirm-release-ready")->assertConflict();
        $this->assertSame('approved', $plan->fresh()->status);
        $this->assertSame('approved', $rollback->fresh()->status);
    }

    public function test_release_ready_requires_all_gates_and_duplicate_is_rejected(): void
    {
        [$ticket] = $this->preparedDocuments();
        foreach ($ticket->releaseChecklistItems as $item) {
            $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/release-checklist/{$item->id}/decision", ['status' => 'completed', 'expected_version' => $item->version])->assertOk();
        }
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/confirm-release-ready")->assertOk()->assertJsonPath('data.status', 'release_ready');
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/confirm-release-ready")->assertConflict();
        $this->assertNotNull($ticket->fresh()->release_ready_at);
    }

    private function preparedDocuments(): array
    {
        $ticket = $this->approvedTicket();
        $plan = $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/release-plan", $this->releasePayload())->assertCreated()->json('data');
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/release-plan/{$plan['id']}/submit")->assertOk();
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/release-plan/{$plan['id']}/approve")->assertOk();
        $rollback = $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/rollback-plan", ['rollback_trigger' => 'Failure threshold exceeded', 'rollback_steps' => ['Restore previous build'], 'data_recovery_steps' => [], 'estimated_rollback_minutes' => 30, 'validation_after_rollback' => ['Health check'], 'responsible_user_id' => $this->pic->id])->assertCreated()->json('data');
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/rollback-plan/{$rollback['id']}/submit")->assertOk();
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/rollback-plan/{$rollback['id']}/approve")->assertOk();

        return [$ticket->fresh('releaseChecklistItems'), TicketReleasePlan::find($plan['id']), TicketRollbackPlan::find($rollback['id'])];
    }

    private function approvedTicket(string $number = 'TIC-P11-APPROVED'): Ticket
    {
        $ticket = $this->ticket($number);
        $approval = $this->requestApproval($ticket)->json('data');
        $business = collect($approval['steps'])->firstWhere('step_type', 'business_approval');
        $technical = collect($approval['steps'])->firstWhere('step_type', 'technical_readiness');
        $this->actingAs($this->manager)->postJson("/api/v1/manager/tickets/{$ticket->id}/business-approval/approve", ['expected_version' => $business['version']])->assertOk();
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/technical-approval/approve", ['expected_version' => $technical['version']])->assertOk();

        return $ticket->fresh();
    }

    private function requestApproval(Ticket $ticket)
    {
        return $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/request-release-approval", $this->approvalPayload());
    }

    private function ticket(string $number = 'TIC-P11-001'): Ticket
    {
        $ticket = Ticket::create(['ticket_number' => $number, 'title' => 'Phase 11 ticket', 'description' => 'Approval release test', 'requester_id' => $this->requester->id, 'division_id' => $this->division->id, 'current_division_id' => $this->division->id, 'branch_id' => $this->branch->id, 'ticket_category_id' => $this->category->id, 'requested_priority_id' => $this->priority->id, 'final_priority_id' => $this->priority->id, 'current_assignee_id' => $this->pic->id, 'status' => TicketStatus::UatApproved, 'progress_percentage' => 100, 'uat_cycle_number' => 1, 'latest_uat_result' => 'accepted', 'uat_approved_at' => now()]);
        $ticket->assignments()->create(['assigned_by' => $this->itLead->id, 'assigned_to' => $this->pic->id, 'division_id' => $this->division->id, 'is_current' => true, 'started_at' => now()]);

        return $ticket;
    }

    private function approvalPayload(): array
    {
        return ['summary' => 'Business and technical release approval request.', 'business_impact' => 'Improves operations.', 'release_risk_level' => 'medium', 'proposed_release_at' => now()->addDay()->toISOString()];
    }

    private function releasePayload(): array
    {
        return ['release_owner_id' => $this->pic->id, 'release_type' => 'normal', 'target_environment' => 'production', 'change_summary' => 'Release approved functionality.', 'technical_summary' => 'Deploy application build after validations.', 'affected_components' => ['application'], 'dependencies' => [], 'database_changes' => null, 'data_migration_required' => false, 'downtime_required' => false, 'estimated_downtime_minutes' => 0, 'proposed_start_at' => now()->addDay()->toISOString(), 'estimated_duration_minutes' => 60, 'validation_steps' => ['Run smoke test'], 'monitoring_plan' => ['Review health metrics'], 'communication_notes' => 'Notify stakeholders.'];
    }

    private function user(string $role, string $email, Division $division): User
    {
        return User::create(['role_id' => Role::where('key', $role)->firstOrFail()->id, 'division_id' => $division->id, 'branch_id' => $this->branch?->id, 'name' => ucfirst($role), 'email' => $email, 'password' => bcrypt('password'), 'is_active' => true]);
    }
}
