<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketUatFinding;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketUatTest extends TestCase
{
    use RefreshDatabase;

    private User $itLead;

    private User $requester;

    private User $otherRequester;

    private User $pic;

    private User $otherPic;

    private User $qa;

    private User $supervisor;

    private User $executive;

    private Division $division;

    private Branch $branch;

    private TicketCategory $category;

    private TicketPriority $priority;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->division = Division::create(['code' => 'IT', 'name' => 'Information Technology', 'is_active' => true]);
        $this->branch = Branch::create(['code' => 'JKT', 'name' => 'Jakarta', 'is_active' => true]);

        $rItLead = Role::where('key', 'it_lead')->first();
        $rRequester = Role::where('key', 'requester')->first();
        $rPic = Role::where('key', 'pic')->first();
        $rQa = Role::where('key', 'qa')->first();
        $rSupervisor = Role::where('key', 'supervisor')->first();
        $rExecutive = Role::where('key', 'executive')->first();

        $this->itLead = User::create(['role_id' => $rItLead->id, 'division_id' => $this->division->id, 'branch_id' => $this->branch->id, 'name' => 'IT Lead', 'email' => 'itlead@tichub.local', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->requester = User::create(['role_id' => $rRequester->id, 'division_id' => $this->division->id, 'branch_id' => $this->branch->id, 'name' => 'Requester Owner', 'email' => 'requester@tichub.local', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->otherRequester = User::create(['role_id' => $rRequester->id, 'division_id' => $this->division->id, 'branch_id' => $this->branch->id, 'name' => 'Requester Other', 'email' => 'otherreq@tichub.local', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->pic = User::create(['role_id' => $rPic->id, 'division_id' => $this->division->id, 'branch_id' => $this->branch->id, 'name' => 'PIC Active', 'email' => 'pic@tichub.local', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->otherPic = User::create(['role_id' => $rPic->id, 'division_id' => $this->division->id, 'branch_id' => $this->branch->id, 'name' => 'PIC Other', 'email' => 'otherpic@tichub.local', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->qa = User::create(['role_id' => $rQa->id, 'division_id' => $this->division->id, 'branch_id' => $this->branch->id, 'name' => 'QA Active', 'email' => 'qa@tichub.local', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->supervisor = User::create(['role_id' => $rSupervisor->id, 'division_id' => $this->division->id, 'branch_id' => $this->branch->id, 'name' => 'Supervisor Active', 'email' => 'supervisor@tichub.local', 'password' => bcrypt('password'), 'is_active' => true]);
        $this->executive = User::create(['role_id' => $rExecutive->id, 'division_id' => $this->division->id, 'branch_id' => $this->branch->id, 'name' => 'Executive', 'email' => 'executive@tichub.local', 'password' => bcrypt('password'), 'is_active' => true]);

        $this->category = TicketCategory::create(['code' => 'INCIDENT', 'name' => 'Incident', 'type' => 'incident', 'is_active' => true]);
        $this->priority = TicketPriority::create(['key' => 'critical', 'name' => 'Critical', 'level' => 4, 'is_active' => true]);
    }

    private function createReadyForUatTicket(): Ticket
    {
        $t = Ticket::create([
            'ticket_number' => 'TIC-2026-000001',
            'title' => 'Sample Ticket UAT',
            'description' => 'Verify UAT execution',
            'requester_id' => $this->requester->id,
            'division_id' => $this->division->id,
            'current_division_id' => $this->division->id,
            'branch_id' => $this->branch->id,
            'ticket_category_id' => $this->category->id,
            'requested_priority_id' => $this->priority->id,
            'final_priority_id' => $this->priority->id,
            'current_assignee_id' => $this->pic->id,
            'status' => TicketStatus::ReadyForUat,
            'progress_percentage' => 100,
            'development_started_at' => now()->subDays(2),
            'development_completed_at' => now()->subDay(),
            'internal_testing_started_at' => now()->subDay(),
            'internal_testing_completed_at' => now()->subDay(),
            'ready_for_qa_at' => now()->subDay(),
            'ready_for_uat_at' => now()->subDay(),
        ]);

        $t->assignments()->create([
            'assigned_by' => $this->itLead->id,
            'assigned_to' => $this->pic->id,
            'division_id' => $this->division->id,
            'is_current' => true,
            'started_at' => now(),
        ]);

        return $t;
    }

    public function test_it_lead_can_view_uat_assignment_queue(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $this->actingAs($this->itLead)->getJson('/api/v1/it-lead/uat-assignment-queue')
            ->assertOk()
            ->assertJsonFragment(['id' => $ticket->id, 'status' => 'ready_for_uat']);
    }

    public function test_other_roles_cannot_view_uat_assignment_queue(): void
    {
        $this->actingAs($this->requester)->getJson('/api/v1/it-lead/uat-assignment-queue')->assertForbidden();
        $this->actingAs($this->pic)->getJson('/api/v1/it-lead/uat-assignment-queue')->assertForbidden();
        $this->actingAs($this->qa)->getJson('/api/v1/it-lead/uat-assignment-queue')->assertForbidden();
    }

    public function test_it_lead_can_assign_requester_as_uat_assignee(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $response = $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign-uat", [
            'requester_user_id' => $this->requester->id,
            'notes' => 'Perform UAT carefully.',
        ])->assertOk();

        $this->assertEquals(TicketStatus::UatAssignment, $ticket->fresh()->status);
        $this->assertEquals($this->requester->id, $ticket->fresh()->uat_assignee_id);
    }

    public function test_it_lead_cannot_assign_non_owner_requester(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign-uat", [
            'requester_user_id' => $this->otherRequester->id,
        ])->assertStatus(409);
    }

    public function test_duplicate_uat_assignment_returns_409_conflict(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign-uat", [
            'requester_user_id' => $this->requester->id,
        ])->assertOk();

        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign-uat", [
            'requester_user_id' => $this->requester->id,
        ])->assertStatus(409);
    }

    public function test_requester_can_view_own_uat_assignment(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign-uat", [
            'requester_user_id' => $this->requester->id,
        ]);

        $this->actingAs($this->requester)->getJson('/api/v1/requester/uat-assignments')
            ->assertOk()
            ->assertJsonFragment(['id' => $ticket->id]);
    }

    public function test_other_requester_cannot_view_assigned_uat(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign-uat", [
            'requester_user_id' => $this->requester->id,
        ]);

        $this->actingAs($this->otherRequester)->getJson("/api/v1/requester/tickets/{$ticket->id}/uat")
            ->assertForbidden();
    }

    public function test_requester_can_start_uat(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $this->actingAs($this->itLead)->postJson("/api/v1/it-lead/tickets/{$ticket->id}/assign-uat", [
            'requester_user_id' => $this->requester->id,
        ]);

        $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat/start")
            ->assertOk();

        $this->assertEquals(TicketStatus::UatInProgress, $ticket->fresh()->status);
        $this->assertEquals(1, $ticket->fresh()->uat_cycle_number);
    }

    public function test_starting_uat_from_invalid_status_is_rejected(): void
    {
        $ticket = $this->createReadyForUatTicket(); // Status is ready_for_uat, not uat_assignment
        $ticket->uat_assignee_id = $this->requester->id;
        $ticket->save();

        $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat/start")
            ->assertStatus(409);
    }

    public function test_scenario_creation_validation(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $ticket->uat_assignee_id = $this->requester->id;
        $ticket->status = TicketStatus::UatInProgress;
        $ticket->save();

        $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat-scenarios", [
            'scenario_number' => 'USC-01',
            'title' => 'Verify login',
            'business_objective' => 'User can log in',
            'steps' => [], // Empty steps
            'expected_result' => 'Dashboard loaded',
            'acceptance_criteria' => ['Succeeds'],
            'priority' => 'high',
        ])->assertStatus(422);
    }

    public function test_scenario_number_must_be_unique_per_ticket(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $ticket->uat_assignee_id = $this->requester->id;
        $ticket->status = TicketStatus::UatInProgress;
        $ticket->save();

        $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat-scenarios", [
            'scenario_number' => 'USC-01',
            'title' => 'Verify login 1',
            'business_objective' => 'User can log in',
            'steps' => ['Step 1'],
            'expected_result' => 'Dashboard loaded',
            'acceptance_criteria' => ['Succeeds'],
            'priority' => 'high',
        ])->assertCreated();

        $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat-scenarios", [
            'scenario_number' => 'USC-01',
            'title' => 'Verify login 2',
            'business_objective' => 'User can log in',
            'steps' => ['Step 1'],
            'expected_result' => 'Dashboard loaded',
            'acceptance_criteria' => ['Succeeds'],
            'priority' => 'high',
        ])->assertStatus(422);
    }

    public function test_requester_can_start_run_and_cannot_have_multiple_active_runs(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $ticket->uat_assignee_id = $this->requester->id;
        $ticket->status = TicketStatus::UatInProgress;
        $ticket->save();

        $ticket->uatScenarios()->create([
            'created_by' => $this->requester->id,
            'scenario_number' => 'USC-01',
            'title' => 'Sample',
            'business_objective' => 'Objective',
            'steps' => ['Step 1'],
            'expected_result' => 'Expected',
            'acceptance_criteria' => ['Criteria'],
            'priority' => 'medium',
        ]);

        $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat-runs", [
            'environment' => 'staging',
        ])->assertCreated();

        // Second run must fail with 409
        $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat-runs", [
            'environment' => 'staging',
        ])->assertStatus(409);
    }

    public function test_requester_can_save_result_and_rejected_requires_notes(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $ticket->uat_assignee_id = $this->requester->id;
        $ticket->status = TicketStatus::UatInProgress;
        $ticket->save();

        $scenario = $ticket->uatScenarios()->create([
            'created_by' => $this->requester->id,
            'scenario_number' => 'USC-01',
            'title' => 'Sample',
            'business_objective' => 'Objective',
            'steps' => ['Step 1'],
            'expected_result' => 'Expected',
            'acceptance_criteria' => ['Criteria'],
            'priority' => 'medium',
        ]);

        $run = $ticket->uatRuns()->create([
            'requester_id' => $this->requester->id,
            'cycle_number' => 1,
            'run_number' => 1,
            'environment' => 'staging',
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        // Rejected without notes or actual result must fail with 409/422 validation rules
        $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat-runs/{$run->id}/results", [
            'uat_scenario_id' => $scenario->id,
            'status' => 'rejected',
        ])->assertStatus(409);

        // Succeeds with notes
        $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat-runs/{$run->id}/results", [
            'uat_scenario_id' => $scenario->id,
            'status' => 'rejected',
            'notes' => 'Failed on login screen',
        ])->assertOk();
    }

    public function test_rejected_uat_run_without_finding_cannot_be_completed(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $ticket->uat_assignee_id = $this->requester->id;
        $ticket->status = TicketStatus::UatInProgress;
        $ticket->save();

        $scenario = $ticket->uatScenarios()->create([
            'created_by' => $this->requester->id,
            'scenario_number' => 'USC-01',
            'title' => 'Sample',
            'business_objective' => 'Objective',
            'steps' => ['Step 1'],
            'expected_result' => 'Expected',
            'acceptance_criteria' => ['Criteria'],
            'priority' => 'medium',
        ]);

        $run = $ticket->uatRuns()->create([
            'requester_id' => $this->requester->id,
            'cycle_number' => 1,
            'run_number' => 1,
            'environment' => 'staging',
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        $run->results()->create([
            'uat_scenario_id' => $scenario->id,
            'executed_by' => $this->requester->id,
            'status' => 'rejected',
            'notes' => 'Failed',
            'executed_at' => now(),
        ]);

        // Complete run without UAT finding must fail with 409
        $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat-runs/{$run->id}/complete")
            ->assertStatus(409);
    }

    public function test_all_scenarios_must_be_executed_before_completion(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $ticket->uat_assignee_id = $this->requester->id;
        $ticket->status = TicketStatus::UatInProgress;
        $ticket->save();

        $ticket->uatScenarios()->create([
            'created_by' => $this->requester->id,
            'scenario_number' => 'USC-01',
            'title' => 'Sample 1',
            'business_objective' => 'Obj 1',
            'steps' => ['Step 1'],
            'expected_result' => 'Expected 1',
            'acceptance_criteria' => ['Criteria 1'],
            'priority' => 'medium',
        ]);
        $ticket->uatScenarios()->create([
            'created_by' => $this->requester->id,
            'scenario_number' => 'USC-02',
            'title' => 'Sample 2',
            'business_objective' => 'Obj 2',
            'steps' => ['Step 2'],
            'expected_result' => 'Expected 2',
            'acceptance_criteria' => ['Criteria 2'],
            'priority' => 'medium',
        ]);

        $run = $ticket->uatRuns()->create([
            'requester_id' => $this->requester->id,
            'cycle_number' => 1,
            'run_number' => 1,
            'environment' => 'staging',
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        // Record only one result
        $run->results()->create([
            'uat_scenario_id' => $ticket->uatScenarios->first()->id,
            'executed_by' => $this->requester->id,
            'status' => 'accepted',
            'executed_at' => now(),
        ]);

        $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat-runs/{$run->id}/complete")
            ->assertStatus(409);
    }

    public function test_accepted_uat_run_results_in_uat_approved(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $ticket->uat_assignee_id = $this->requester->id;
        $ticket->status = TicketStatus::UatInProgress;
        $ticket->save();

        $scenario = $ticket->uatScenarios()->create([
            'created_by' => $this->requester->id,
            'scenario_number' => 'USC-01',
            'title' => 'Sample',
            'business_objective' => 'Objective',
            'steps' => ['Step 1'],
            'expected_result' => 'Expected',
            'acceptance_criteria' => ['Criteria'],
            'priority' => 'medium',
        ]);

        $run = $ticket->uatRuns()->create([
            'requester_id' => $this->requester->id,
            'cycle_number' => 1,
            'run_number' => 1,
            'environment' => 'staging',
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        $run->results()->create([
            'uat_scenario_id' => $scenario->id,
            'executed_by' => $this->requester->id,
            'status' => 'accepted',
            'executed_at' => now(),
        ]);

        $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat-runs/{$run->id}/complete")
            ->assertOk();

        $this->assertEquals(TicketStatus::UatApproved, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->uat_approved_at);
    }

    public function test_rejected_uat_run_results_in_uat_failed_and_development_in_progress(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $ticket->uat_assignee_id = $this->requester->id;
        $ticket->status = TicketStatus::UatInProgress;
        $ticket->save();

        $scenario = $ticket->uatScenarios()->create([
            'created_by' => $this->requester->id,
            'scenario_number' => 'USC-01',
            'title' => 'Sample',
            'business_objective' => 'Objective',
            'steps' => ['Step 1'],
            'expected_result' => 'Expected',
            'acceptance_criteria' => ['Criteria'],
            'priority' => 'medium',
        ]);

        $run = $ticket->uatRuns()->create([
            'requester_id' => $this->requester->id,
            'cycle_number' => 1,
            'run_number' => 1,
            'environment' => 'staging',
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        $run->results()->create([
            'uat_scenario_id' => $scenario->id,
            'executed_by' => $this->requester->id,
            'status' => 'rejected',
            'notes' => 'Failed',
            'executed_at' => now(),
        ]);

        $ticket->uatFindings()->create([
            'uat_run_id' => $run->id,
            'uat_scenario_id' => $scenario->id,
            'reported_by' => $this->requester->id,
            'assigned_to' => $this->pic->id,
            'finding_number' => 'FND-001',
            'title' => 'Bug login',
            'description' => 'Failed login screen',
            'business_impact' => 'Cannot log in',
            'severity' => 'major',
            'status' => 'open',
        ]);

        $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat-runs/{$run->id}/complete")
            ->assertOk();

        // Ticket status should be development_in_progress
        $this->assertEquals(TicketStatus::DevelopmentInProgress, $ticket->fresh()->status);
        $this->assertLessThanOrEqual(90, $ticket->fresh()->progress_percentage);
    }

    public function test_finding_number_unique_and_history_immutable(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $ticket->uat_assignee_id = $this->requester->id;
        $ticket->status = TicketStatus::UatInProgress;
        $ticket->save();

        $run = $ticket->uatRuns()->create([
            'requester_id' => $this->requester->id,
            'cycle_number' => 1,
            'run_number' => 1,
            'environment' => 'staging',
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        $scenario = $ticket->uatScenarios()->create([
            'created_by' => $this->requester->id,
            'scenario_number' => 'USC-01',
            'title' => 'Scenario with finding',
            'business_objective' => 'Objective',
            'steps' => ['Step 1'],
            'expected_result' => 'Expected',
            'acceptance_criteria' => ['Criteria'],
            'priority' => 'medium',
        ]);
        $run->results()->create([
            'uat_scenario_id' => $scenario->id,
            'executed_by' => $this->requester->id,
            'status' => 'rejected',
            'notes' => 'Failed',
            'executed_at' => now(),
        ]);

        $f1 = $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat-findings", [
            'uat_run_id' => $run->id,
            'uat_scenario_id' => $scenario->id,
            'title' => 'UAT Bug 1',
            'description' => 'Description 1',
            'business_impact' => 'High',
            'severity' => 'major',
        ])->assertOk()->json('data.id');

        $f2 = $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat-findings", [
            'uat_run_id' => $run->id,
            'uat_scenario_id' => $scenario->id,
            'title' => 'UAT Bug 2',
            'description' => 'Description 2',
            'business_impact' => 'High',
            'severity' => 'major',
        ])->assertOk()->json('data.id');

        $f1_number = TicketUatFinding::find($f1)->finding_number;
        $f2_number = TicketUatFinding::find($f2)->finding_number;

        $this->assertNotEquals($f1_number, $f2_number);

        // Check history
        $this->assertDatabaseHas('ticket_uat_finding_histories', [
            'uat_finding_id' => $f1,
            'to_status' => 'open',
            'action' => 'created',
        ]);
    }

    public function test_pic_can_view_findings_but_other_pics_cannot(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $this->actingAs($this->pic)->getJson("/api/v1/pic/tickets/{$ticket->id}/uat-findings")
            ->assertOk();

        $this->actingAs($this->otherPic)->getJson("/api/v1/pic/tickets/{$ticket->id}/uat-findings")
            ->assertForbidden();
    }

    public function test_pic_can_start_and_resolve_findings(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $ticket->uat_assignee_id = $this->requester->id;
        $ticket->status = TicketStatus::DevelopmentInProgress;
        $ticket->save();

        $run = $ticket->uatRuns()->create([
            'requester_id' => $this->requester->id,
            'cycle_number' => 1,
            'run_number' => 1,
            'environment' => 'staging',
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        $finding = $ticket->uatFindings()->create([
            'uat_run_id' => $run->id,
            'reported_by' => $this->requester->id,
            'assigned_to' => $this->pic->id,
            'finding_number' => 'FND-1',
            'title' => 'Bug',
            'description' => 'Error',
            'business_impact' => 'None',
            'severity' => 'minor',
            'status' => 'open',
        ]);

        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/uat-findings/{$finding->id}/start")
            ->assertOk();
        $this->assertEquals('in_progress', $finding->fresh()->status);

        // Resolve finding requires resolution notes
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/uat-findings/{$finding->id}/resolve", [])
            ->assertStatus(422);

        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/uat-findings/{$finding->id}/resolve", [
            'resolution_notes' => 'Fixed the variable scope.',
        ])->assertOk();
        $this->assertEquals('resolved', $finding->fresh()->status);
    }

    public function test_pic_cannot_verify_and_requester_cannot_resolve_findings(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $ticket->uat_assignee_id = $this->requester->id;
        $ticket->status = TicketStatus::UatInProgress;
        $ticket->save();

        $run = $ticket->uatRuns()->create(['requester_id' => $this->requester->id, 'cycle_number' => 1, 'run_number' => 1, 'environment' => 'staging', 'started_at' => now(), 'status' => 'in_progress']);
        $finding = $ticket->uatFindings()->create([
            'uat_run_id' => $run->id, 'reported_by' => $this->requester->id, 'assigned_to' => $this->pic->id, 'finding_number' => 'FND-1',
            'title' => 'Bug', 'description' => 'Error', 'business_impact' => 'None', 'severity' => 'minor', 'status' => 'resolved',
        ]);

        // PIC try verify must fail
        $this->actingAs($this->pic)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat-findings/{$finding->id}/verify")
            ->assertForbidden();

        // Requester try resolve must fail
        $this->actingAs($this->requester)->postJson("/api/v1/pic/tickets/{$ticket->id}/uat-findings/{$finding->id}/resolve", ['resolution_notes' => 'Fix'])
            ->assertForbidden();
    }

    public function test_submit_retest_preconditions(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $ticket->uat_assignee_id = $this->requester->id;
        $ticket->status = TicketStatus::DevelopmentInProgress;
        $ticket->progress_percentage = 90; // Less than 100
        $ticket->save();

        // Submit UAT retest must fail with progress < 100
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/submit-uat-retest", [
            'requires_qa_retest' => false,
        ])->assertStatus(409);

        // Progress set to 100, but no rework worklog, and no passed internal test run
        $ticket->progress_percentage = 100;
        $ticket->save();

        // Trigger history of uat_failed to calculate last failure timestamp
        $ticket->histories()->create([
            'from_status' => 'uat_in_progress',
            'to_status' => 'uat_failed',
            'action' => 'uat_failed',
            'actor_id' => $this->requester->id,
            'actor_role' => 'requester',
        ]);

        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/submit-uat-retest", [
            'requires_qa_retest' => false,
        ])->assertStatus(409);

        // Add rework worklog
        $ticket->worklogs()->create([
            'user_id' => $this->pic->id,
            'work_date' => now()->toDateString(),
            'minutes_spent' => 60,
            'activity_type' => 'rework',
            'description' => 'Fixing bug.',
            'progress_before' => 90,
            'progress_after' => 100,
        ]);

        // Still fails because no passed internal test run
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/submit-uat-retest", [
            'requires_qa_retest' => false,
        ])->assertStatus(409);

        // Add passed internal test run
        $ticket->internalTestRuns()->create([
            'executed_by' => $this->pic->id,
            'run_number' => 1,
            'environment' => 'staging',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'status' => 'passed',
        ]);

        // Submit now succeeds
        $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/submit-uat-retest", [
            'requires_qa_retest' => false,
        ])->assertOk();

        $this->assertEquals(TicketStatus::UatRetest, $ticket->fresh()->status);
    }

    public function test_requester_can_verify_and_reopen_findings(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $ticket->uat_assignee_id = $this->requester->id;
        $ticket->status = TicketStatus::UatRetest;
        $ticket->save();

        $run = $ticket->uatRuns()->create(['requester_id' => $this->requester->id, 'cycle_number' => 1, 'run_number' => 1, 'environment' => 'staging', 'started_at' => now(), 'status' => 'in_progress']);
        $finding = $ticket->uatFindings()->create([
            'uat_run_id' => $run->id, 'reported_by' => $this->requester->id, 'assigned_to' => $this->pic->id, 'finding_number' => 'FND-1',
            'title' => 'Bug', 'description' => 'Error', 'business_impact' => 'None', 'severity' => 'minor', 'status' => 'retest',
        ]);

        $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat-findings/{$finding->id}/verify")
            ->assertOk();
        $this->assertEquals('verified', $finding->fresh()->status);

        // Reopen finding
        $finding->fresh()->update(['status' => 'retest']);

        $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat-findings/{$finding->id}/reopen")
            ->assertOk();
        $this->assertEquals('reopened', $finding->fresh()->status);
    }

    public function test_retest_run_rejection_returns_to_development(): void
    {
        $ticket = $this->createReadyForUatTicket();
        $ticket->uat_assignee_id = $this->requester->id;
        $ticket->status = TicketStatus::UatRetest;
        $ticket->save();

        $scenario = $ticket->uatScenarios()->create([
            'created_by' => $this->requester->id, 'scenario_number' => 'USC-01', 'title' => 'Sample', 'business_objective' => 'Objective',
            'steps' => ['Step 1'], 'expected_result' => 'Expected', 'acceptance_criteria' => ['Criteria'], 'priority' => 'medium',
        ]);

        // Requester start UAT retest
        $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat/start")->assertOk();
        $this->assertEquals(1, $ticket->fresh()->uat_cycle_number); // Cycle is incremented

        $run = $ticket->uatRuns()->create(['requester_id' => $this->requester->id, 'cycle_number' => 1, 'run_number' => 2, 'environment' => 'staging', 'started_at' => now(), 'status' => 'in_progress']);

        $run->results()->create([
            'uat_scenario_id' => $scenario->id, 'executed_by' => $this->requester->id, 'status' => 'rejected', 'notes' => 'Bug still present', 'executed_at' => now(),
        ]);

        $ticket->uatFindings()->create([
            'uat_run_id' => $run->id, 'uat_scenario_id' => $scenario->id, 'reported_by' => $this->requester->id, 'assigned_to' => $this->pic->id, 'finding_number' => 'FND-002',
            'title' => 'Bug login 2', 'description' => 'Failed login screen', 'business_impact' => 'Cannot log in', 'severity' => 'major', 'status' => 'open',
        ]);

        // Complete run (fails UAT retest)
        $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat-runs/{$run->id}/complete")->assertOk();

        $this->assertEquals(TicketStatus::DevelopmentInProgress, $ticket->fresh()->status);
    }

    public function test_nested_resources_are_validated_properly(): void
    {
        $t1 = $this->createReadyForUatTicket();
        $t2 = Ticket::create([
            'ticket_number' => 'TIC-2026-000002', 'title' => 'Sample Ticket 2', 'description' => 'UAT 2',
            'requester_id' => $this->requester->id, 'division_id' => $this->division->id, 'current_division_id' => $this->division->id, 'branch_id' => $this->branch->id,
            'ticket_category_id' => $this->category->id, 'requested_priority_id' => $this->priority->id, 'final_priority_id' => $this->priority->id,
            'current_assignee_id' => $this->pic->id, 'status' => TicketStatus::ReadyForUat,
        ]);

        $t1->uat_assignee_id = $this->requester->id;
        $t1->status = TicketStatus::UatInProgress;
        $t1->save();

        $scenarioOnT1 = $t1->uatScenarios()->create([
            'created_by' => $this->requester->id, 'scenario_number' => 'USC-01', 'title' => 'Sample', 'business_objective' => 'Objective',
            'steps' => ['Step 1'], 'expected_result' => 'Expected', 'acceptance_criteria' => ['Criteria'], 'priority' => 'medium',
        ]);

        // Update scenario on T1 through T2 endpoint must fail with 404
        $this->actingAs($this->requester)->putJson("/api/v1/requester/tickets/{$t2->id}/uat-scenarios/{$scenarioOnT1->id}", [
            'scenario_number' => 'USC-01', 'title' => 'Verify T2', 'business_objective' => 'None', 'steps' => ['Step 1'], 'expected_result' => 'Fix', 'acceptance_criteria' => ['Ok'], 'priority' => 'medium',
        ])->assertStatus(404);
    }

    public function test_uat_evidence_rejects_cross_ticket_finding_and_hides_pic_evidence(): void
    {
        Storage::fake('local');
        $ticket = $this->createReadyForUatTicket();
        $ticket->uat_assignee_id = $this->requester->id;
        $ticket->status = TicketStatus::UatInProgress;
        $ticket->ticket_number = 'TIC-2026-000099';
        $ticket->save();
        $otherTicket = $this->createReadyForUatTicket();
        $foreignFinding = $otherTicket->uatFindings()->create([
            'uat_run_id' => $otherTicket->uatRuns()->create([
                'requester_id' => $this->requester->id,
                'cycle_number' => 1,
                'run_number' => 1,
                'environment' => 'staging',
                'started_at' => now(),
                'status' => 'in_progress',
            ])->id,
            'reported_by' => $this->requester->id,
            'assigned_to' => $this->pic->id,
            'finding_number' => 'FND-FOREIGN',
            'title' => 'Foreign finding',
            'description' => 'Foreign description',
            'business_impact' => 'Impact',
            'severity' => 'major',
            'status' => 'open',
        ]);

        $this->actingAs($this->requester)->postJson("/api/v1/requester/tickets/{$ticket->id}/uat-evidence", [
            'file' => UploadedFile::fake()->create('evidence.pdf', 10, 'application/pdf'),
            'category' => 'uat_evidence',
            'uat_finding_id' => $foreignFinding->id,
        ])->assertStatus(422);

        $ticket->status = TicketStatus::DevelopmentInProgress;
        $ticket->save();
        $ownFinding = $ticket->uatFindings()->create([
            'uat_run_id' => $ticket->uatRuns()->create([
                'requester_id' => $this->requester->id,
                'cycle_number' => 1,
                'run_number' => 2,
                'environment' => 'staging',
                'started_at' => now(),
                'status' => 'completed',
            ])->id,
            'reported_by' => $this->requester->id,
            'assigned_to' => $this->pic->id,
            'finding_number' => 'FND-OWN',
            'title' => 'Own finding',
            'description' => 'Own description',
            'business_impact' => 'Impact',
            'severity' => 'major',
            'status' => 'resolved',
        ]);

        $upload = $this->actingAs($this->pic)->postJson("/api/v1/pic/tickets/{$ticket->id}/uat-evidence", [
            'file' => UploadedFile::fake()->create('retest.pdf', 10, 'application/pdf'),
            'category' => 'uat_retest_evidence',
            'uat_finding_id' => $ownFinding->id,
        ])->assertCreated();

        $attachmentId = $upload->json('data.id');
        $this->actingAs($this->requester)->get("/api/v1/tickets/{$ticket->id}/attachments/{$attachmentId}/download")
            ->assertForbidden();
    }
}
