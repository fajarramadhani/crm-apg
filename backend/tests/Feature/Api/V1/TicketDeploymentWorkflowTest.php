<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TicketStatus;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketDeploymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $itLead;

    private User $requester;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->seed(RoleSeeder::class);

        $division = Division::firstOrCreate(['code' => 'IT'], ['name' => 'IT', 'is_active' => true]);
        $branch = Branch::firstOrCreate(['code' => 'JKT'], ['name' => 'Jakarta', 'is_active' => true]);
        $category = TicketCategory::firstOrCreate(['code' => 'CHANGE'], ['name' => 'Change', 'type' => 'change', 'is_active' => true]);
        $priority = TicketPriority::firstOrCreate(['key' => 'high'], ['name' => 'High', 'level' => 3, 'is_active' => true]);

        $this->itLead = User::create([
            'role_id' => Role::where('key', 'it_lead')->first()->id,
            'division_id' => $division->id,
            'name' => 'IT Lead',
            'email' => 'lead@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->requester = User::create([
            'role_id' => Role::where('key', 'requester')->first()->id,
            'division_id' => $division->id,
            'name' => 'Requester',
            'email' => 'req@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->ticket = Ticket::create([
            'ticket_number' => 'TIC-DEP-001',
            'title' => 'Deploy Feature',
            'description' => 'Test deployment',
            'requester_id' => $this->requester->id,
            'division_id' => $division->id,
            'current_division_id' => $division->id,
            'branch_id' => $branch->id,
            'ticket_category_id' => $category->id,
            'requested_priority_id' => $priority->id,
            'final_priority_id' => $priority->id,
            'current_assignee_id' => $this->itLead->id,
            'status' => TicketStatus::ReleaseReady,
            'release_owner_id' => $this->itLead->id,
            'progress_percentage' => 100,
        ]);

        $approval = $this->ticket->approvalRequests()->create([
            'status' => 'approved',
            'summary' => 'test',
            'business_impact' => 'test',
            'release_risk_level' => 'low',
            'proposed_release_at' => now(),
            'requested_by' => $this->itLead->id,
            'requested_at' => now(),
            'cycle_number' => 1,
        ]);

        $releasePlan = $this->ticket->releasePlans()->create([
            'approval_request_id' => $approval->id,
            'version' => 1,
            'status' => 'approved',
            'created_by' => $this->itLead->id,
            'release_owner_id' => $this->itLead->id,
            'proposed_start_at' => now(),
            'release_type' => 'normal',
            'target_environment' => 'production',
            'change_summary' => 'test',
            'technical_summary' => 'test',
            'deployment_steps' => [
                [
                    'title' => 'Copy files',
                    'description' => 'Copy build files to server',
                    'is_required' => true,
                ],
            ],
            'estimated_duration_minutes' => 60,
            'affected_components' => ['app'],
            'validation_steps' => [
                [
                    'title' => 'Test',
                    'description' => 'Test validation step',
                    'is_required' => true,
                ],
            ],
            'monitoring_plan' => ['test'],
        ]);

        $this->ticket->rollbackPlans()->create([
            'release_plan_id' => $releasePlan->id,
            'version' => 1,
            'status' => 'approved',
            'created_by' => $this->itLead->id,
            'rollback_trigger' => 'test',
            'rollback_steps' => 'test',
            'estimated_rollback_minutes' => 60,
            'validation_after_rollback' => 'test',
            'responsible_user_id' => $this->itLead->id,
        ]);
    }

    public function test_it_lead_can_schedule_and_start_deployment()
    {
        $this->actingAs($this->itLead);

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/schedule", [
            'scheduled_start_at' => now()->addHour()->toDateTimeString(),
            'scheduled_end_at' => now()->addHours(2)->toDateTimeString(),
        ]);
        $response->assertStatus(201);
        $this->assertEquals(TicketStatus::DeploymentScheduled->value, $response->json('ticket.status'));

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/start", [
            'notes' => 'Starting now',
        ]);
        $response->assertStatus(200);
        $this->assertEquals(TicketStatus::DeploymentInProgress->value, $response->json('ticket.status'));

        $deployment = $this->ticket->deployments()->latest()->first();
        foreach ($deployment->steps as $step) {
            $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/step", [
                'step_number' => $step->step_number,
                'title' => $step->title,
                'description' => $step->description,
                'status' => 'completed',
            ]);
            $response->assertStatus(200);
        }

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/complete", [
            'summary' => 'Done',
        ]);
        $response->assertStatus(200);
        $this->assertEquals(TicketStatus::Deployed->value, $response->json('ticket.status'));
    }

    public function test_it_lead_can_execute_monitoring()
    {
        $this->ticket->update(['status' => TicketStatus::Deployed]);
        $deployment = $this->ticket->deployments()->create([
            'release_plan_id' => $this->ticket->releasePlans()->first()->id,
            'rollback_plan_id' => $this->ticket->rollbackPlans()->first()->id,
            'cycle_number' => 1,
            'environment' => 'production',
            'release_version' => '1.0.0',
            'deployment_owner_id' => $this->itLead->id,
            'release_owner_id' => $this->itLead->id,
            'approved_by' => $this->itLead->id,
            'deployment_summary' => 'test',
            'status' => 'succeeded',
            'deployment_number' => 'DEP-01',
            'scheduled_start_at' => now(),
        ]);
        $this->ticket->update(['current_deployment_id' => $deployment->id]);

        $this->actingAs($this->itLead);

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/monitoring/start");
        $response->assertStatus(201);
        $this->assertEquals(TicketStatus::Monitoring->value, $response->json('ticket.status'));

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/monitoring/check", [
            'title' => 'Server is up',
            'description' => 'Verify server is reachable',
            'expected_condition' => 'HTTP 200 OK',
            'actual_result' => 'OK',
        ]);
        $response->assertStatus(200);

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/monitoring/complete", [
            'overall_result' => 'success',
        ]);
        $response->assertStatus(200);
        $this->assertEquals(TicketStatus::AwaitingRequesterConfirmation->value, $response->json('ticket.status'));
    }

    public function test_requester_can_confirm_and_close()
    {
        $deployment = $this->ticket->deployments()->create([
            'release_plan_id' => $this->ticket->releasePlans()->first()->id,
            'rollback_plan_id' => $this->ticket->rollbackPlans()->first()->id,
            'cycle_number' => 1,
            'environment' => 'production',
            'release_version' => '1.0.0',
            'deployment_owner_id' => $this->itLead->id,
            'release_owner_id' => $this->itLead->id,
            'approved_by' => $this->itLead->id,
            'deployment_summary' => 'test',
            'status' => 'succeeded',
            'deployment_number' => 'DEP-01',
            'scheduled_start_at' => now(),
        ]);

        $session = $this->ticket->monitoringSessions()->create([
            'deployment_id' => $deployment->id,
            'cycle_number' => 1,
            'started_by' => $this->itLead->id,
            'started_at' => now(),
            'status' => 'completed',
        ]);

        $this->ticket->update([
            'status' => TicketStatus::AwaitingRequesterConfirmation,
            'current_deployment_id' => $deployment->id,
            'current_monitoring_session_id' => $session->id,
        ]);

        $this->actingAs($this->requester);

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/confirmation", [
            'status' => 'accepted',
            'notes' => 'LGTM',
        ]);
        $response->assertStatus(200);

        $this->assertEquals(TicketStatus::AwaitingRequesterConfirmation->value, $response->json('ticket.status'));

        $this->actingAs($this->itLead);
        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/close", [
            'closure_summary' => 'Finished successfully',
            'resolution_summary' => 'Resolved the issue',
            'business_outcome' => 'Business is happy',
        ]);
        $response->assertStatus(200);

        $this->assertEquals(TicketStatus::Closed->value, $response->json('ticket.status'));
    }

    public function test_cannot_schedule_if_not_release_ready()
    {
        $this->ticket->update(['status' => TicketStatus::Draft]);
        $this->actingAs($this->itLead);
        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/schedule", ['scheduled_start_at' => now()->toDateTimeString()]);
        $response->assertStatus(500);
    }

    public function test_cannot_schedule_without_start_time()
    {
        $this->actingAs($this->itLead);
        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/schedule", []);
        $response->assertStatus(422);
    }

    public function test_requester_cannot_schedule_deployment()
    {
        $this->actingAs($this->requester);
        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/schedule", ['scheduled_start_at' => now()->toDateTimeString()]);
        $response->assertStatus(403);
    }

    public function test_cannot_start_deployment_if_not_scheduled()
    {
        $this->actingAs($this->itLead);
        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/start", []);
        $this->assertFalse($response->isSuccessful());
    }

    public function test_cannot_manage_step_if_deployment_not_in_progress()
    {
        $this->actingAs($this->itLead);
        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/schedule", ['scheduled_start_at' => now()->toDateTimeString()]);

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/step", [
            'step_number' => 1,
            'title' => 'Copy files',
            'status' => 'completed',
        ]);
        $this->assertFalse($response->isSuccessful());
    }

    public function test_cannot_complete_deployment_if_steps_pending()
    {
        $this->ticket->releasePlans()->first()->update(['deployment_steps' => json_encode([['action' => 'Deploy', 'is_required' => true]])]);
        $this->actingAs($this->itLead);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/schedule", ['scheduled_start_at' => now()->toDateTimeString()]);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/start", []);
        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/complete", ['summary' => 'Done']);
        $response->assertStatus(400); // 400 is returned by execution service for pending steps.
    }

    public function test_cannot_fail_deployment_without_reason()
    {
        $this->actingAs($this->itLead);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/schedule", ['scheduled_start_at' => now()->toDateTimeString()]);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/start", []);

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/fail", []);
        $response->assertStatus(422);
    }

    public function test_cannot_start_rollback_if_not_failed()
    {
        $this->actingAs($this->itLead);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/schedule", ['scheduled_start_at' => now()->toDateTimeString()]);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/start", []);

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/rollbacks/start", ['reason' => 'Rollback']);
        $this->assertFalse($response->isSuccessful());
    }

    public function test_cannot_start_rollback_without_reason()
    {
        $this->actingAs($this->itLead);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/schedule", ['scheduled_start_at' => now()->toDateTimeString()]);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/start", []);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/fail", ['reason' => 'Failed']);

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/rollbacks/start", []);
        $response->assertStatus(422);
    }

    public function test_cannot_manage_rollback_step_if_not_in_progress()
    {
        $this->actingAs($this->itLead);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/schedule", ['scheduled_start_at' => now()->toDateTimeString()]);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/start", []);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/fail", ['reason' => 'Failed']);

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/rollbacks/step", [
            'step_number' => 1,
            'status' => 'completed',
        ]);
        $this->assertFalse($response->isSuccessful());
    }

    public function test_cannot_complete_rollback_without_summary()
    {
        $this->actingAs($this->itLead);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/schedule", ['scheduled_start_at' => now()->toDateTimeString()]);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/start", []);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/fail", ['reason' => 'Failed']);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/rollbacks/start", ['reason' => 'Rollback']);

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/rollbacks/complete", []);
        $response->assertStatus(422);
    }

    public function test_cannot_start_monitoring_if_not_deployed()
    {
        $this->actingAs($this->itLead);
        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/monitoring/start");
        $this->assertFalse($response->isSuccessful());
    }

    public function test_cannot_add_monitoring_check_if_not_monitoring()
    {
        $this->actingAs($this->itLead);
        $this->ticket->update(['status' => TicketStatus::Deployed]);

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/monitoring/check", [
            'title' => 'Server is up',
            'actual_result' => 'OK',
        ]);
        $this->assertFalse($response->isSuccessful());
    }

    public function test_cannot_add_monitoring_check_without_title()
    {
        $this->actingAs($this->itLead);
        $this->ticket->update(['status' => TicketStatus::Deployed]);
        $deployment = $this->ticket->deployments()->create(['release_plan_id' => 1, 'rollback_plan_id' => 1, 'cycle_number' => 1, 'deployment_number' => 'DEP-01', 'status' => 'succeeded', 'environment' => 'production', 'release_version' => '1.0.0', 'deployment_owner_id' => 1, 'release_owner_id' => 1, 'approved_by' => 1, 'deployment_summary' => 'test', 'scheduled_start_at' => now()]);
        $this->ticket->update(['current_deployment_id' => $deployment->id]);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/monitoring/start");

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/monitoring/check", [
            'actual_result' => 'OK',
        ]);
        $response->assertStatus(422);
    }

    public function test_cannot_report_incident_without_title()
    {
        $this->actingAs($this->itLead);
        $this->ticket->update(['status' => TicketStatus::Deployed]);
        $deployment = $this->ticket->deployments()->create(['release_plan_id' => 1, 'rollback_plan_id' => 1, 'cycle_number' => 1, 'deployment_number' => 'DEP-01', 'status' => 'succeeded', 'environment' => 'production', 'release_version' => '1.0.0', 'deployment_owner_id' => 1, 'release_owner_id' => 1, 'approved_by' => 1, 'deployment_summary' => 'test', 'scheduled_start_at' => now()]);
        $this->ticket->update(['current_deployment_id' => $deployment->id]);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/monitoring/start");

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/monitoring/incident", [
            'description' => 'Server down',
        ]);
        $response->assertStatus(422);
    }

    public function test_reporting_incident_changes_status()
    {
        $this->actingAs($this->itLead);
        $this->ticket->update(['status' => TicketStatus::Deployed]);
        $deployment = $this->ticket->deployments()->create(['release_plan_id' => 1, 'rollback_plan_id' => 1, 'cycle_number' => 1, 'deployment_number' => 'DEP-01', 'status' => 'succeeded', 'environment' => 'production', 'release_version' => '1.0.0', 'deployment_owner_id' => 1, 'release_owner_id' => 1, 'approved_by' => 1, 'deployment_summary' => 'test', 'scheduled_start_at' => now()]);
        $this->ticket->update(['current_deployment_id' => $deployment->id]);
        $this->postJson("/api/v1/tickets/{$this->ticket->id}/monitoring/start");

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/monitoring/incident", ['title' => 'Server down', 'description' => 'App is crashing', 'assigned_to' => 1, 'business_impact' => 'High']);
        $response->assertStatus(200);
        $this->ticket->refresh();
        $this->assertEquals(TicketStatus::PostReleaseIssue, $this->ticket->status);
    }

    public function test_cannot_confirm_if_not_awaiting()
    {
        $this->actingAs($this->requester);
        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/confirmation", [
            'status' => 'accepted',
        ]);
        $this->assertFalse($response->isSuccessful());
    }

    public function test_cannot_confirm_without_status()
    {
        $this->actingAs($this->requester);
        $deployment = $this->ticket->deployments()->create(['release_plan_id' => 1, 'rollback_plan_id' => 1, 'cycle_number' => 1, 'deployment_number' => 'DEP-01', 'status' => 'succeeded', 'environment' => 'production', 'release_version' => '1.0.0', 'deployment_owner_id' => 1, 'release_owner_id' => 1, 'approved_by' => 1, 'deployment_summary' => 'test', 'scheduled_start_at' => now()]);
        $this->ticket->update(['status' => TicketStatus::AwaitingRequesterConfirmation, 'current_deployment_id' => $deployment->id]);
        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/confirmation", []);
        $response->assertStatus(422);
    }

    public function test_cannot_reject_without_reason()
    {
        $this->actingAs($this->requester);
        $deployment = $this->ticket->deployments()->create(['release_plan_id' => 1, 'rollback_plan_id' => 1, 'cycle_number' => 1, 'deployment_number' => 'DEP-01', 'status' => 'succeeded', 'environment' => 'production', 'release_version' => '1.0.0', 'deployment_owner_id' => 1, 'release_owner_id' => 1, 'approved_by' => 1, 'deployment_summary' => 'test', 'scheduled_start_at' => now()]);
        $this->ticket->update(['status' => TicketStatus::AwaitingRequesterConfirmation, 'current_deployment_id' => $deployment->id]);
        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/confirmation", [
            'status' => 'rejected',
        ]);
        $this->assertFalse($response->isSuccessful()); // Expect 500 or 400 from InvalidArgumentException
    }

    public function test_rejecting_confirmation_changes_status_to_dev()
    {
        $this->actingAs($this->requester);
        $deployment = $this->ticket->deployments()->create(['release_plan_id' => 1, 'rollback_plan_id' => 1, 'cycle_number' => 1, 'deployment_number' => 'DEP-01', 'status' => 'succeeded', 'environment' => 'production', 'release_version' => '1.0.0', 'deployment_owner_id' => 1, 'release_owner_id' => 1, 'approved_by' => 1, 'deployment_summary' => 'test', 'scheduled_start_at' => now()]);
        $this->ticket->update(['status' => TicketStatus::AwaitingRequesterConfirmation, 'current_deployment_id' => $deployment->id]);
        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/confirmation", [
            'status' => 'rejected',
            'rejection_reason' => 'Bugs found',
        ]);
        $response->assertStatus(200);

        $this->ticket->refresh();
        $this->assertEquals(TicketStatus::DevelopmentInProgress, $this->ticket->status);
    }

    public function test_cannot_confirm_twice()
    {
        $this->actingAs($this->requester);
        $deployment = $this->ticket->deployments()->create(['release_plan_id' => 1, 'rollback_plan_id' => 1, 'cycle_number' => 1, 'deployment_number' => 'DEP-01', 'status' => 'succeeded', 'environment' => 'production', 'release_version' => '1.0.0', 'deployment_owner_id' => 1, 'release_owner_id' => 1, 'approved_by' => 1, 'deployment_summary' => 'test', 'scheduled_start_at' => now()]);
        $this->ticket->update(['status' => TicketStatus::AwaitingRequesterConfirmation, 'current_deployment_id' => $deployment->id]);
        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/confirmation", [
            'status' => 'accepted',
        ]);

        $response->assertStatus(200);

        // Ticket is still awaiting_requester_confirmation
        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/confirmation", [
            'status' => 'accepted',
        ]);
        $response->assertStatus(409); // Conflict
    }

    public function test_cannot_close_if_not_awaiting_confirmation()
    {
        $this->actingAs($this->itLead);
        $this->ticket->update(['status' => TicketStatus::Deployed]);

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/close", [
            'closure_summary' => 'Done',
            'resolution_summary' => 'Resolved',
            'business_outcome' => 'Happy',
        ]);
        $this->assertFalse($response->isSuccessful());
    }

    public function test_cannot_close_without_summary()
    {
        $this->actingAs($this->itLead);
        $deployment = $this->ticket->deployments()->create(['release_plan_id' => 1, 'rollback_plan_id' => 1, 'cycle_number' => 1, 'deployment_number' => 'DEP-01', 'status' => 'succeeded', 'environment' => 'production', 'release_version' => '1.0.0', 'deployment_owner_id' => 1, 'release_owner_id' => 1, 'approved_by' => 1, 'deployment_summary' => 'test', 'scheduled_start_at' => now()]);
        $this->ticket->update(['status' => TicketStatus::AwaitingRequesterConfirmation, 'current_deployment_id' => $deployment->id]);

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/close", []);
        $response->assertStatus(422);
    }

    public function test_cannot_close_if_confirmation_not_accepted()
    {
        $this->actingAs($this->itLead);
        $deployment = $this->ticket->deployments()->create(['release_plan_id' => 1, 'rollback_plan_id' => 1, 'cycle_number' => 1, 'deployment_number' => 'DEP-01', 'status' => 'succeeded', 'environment' => 'production', 'release_version' => '1.0.0', 'deployment_owner_id' => 1, 'release_owner_id' => 1, 'approved_by' => 1, 'deployment_summary' => 'test', 'scheduled_start_at' => now()]);
        $this->ticket->update(['status' => TicketStatus::AwaitingRequesterConfirmation, 'current_deployment_id' => $deployment->id]);

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/close", [
            'closure_summary' => 'Done',
            'resolution_summary' => 'Resolved',
            'business_outcome' => 'Happy',
        ]);
        $this->assertFalse($response->isSuccessful()); // No confirmation exists
    }

    public function test_cannot_close_already_closed_ticket()
    {
        $deployment = $this->ticket->deployments()->create(['release_plan_id' => 1, 'rollback_plan_id' => 1, 'cycle_number' => 1, 'deployment_number' => 'DEP-01', 'status' => 'succeeded', 'environment' => 'production', 'release_version' => '1.0.0', 'deployment_owner_id' => 1, 'release_owner_id' => 1, 'approved_by' => 1, 'deployment_summary' => 'test', 'scheduled_start_at' => now()]);
        $this->ticket->update(['status' => TicketStatus::AwaitingRequesterConfirmation, 'current_deployment_id' => $deployment->id]);

        $this->actingAs($this->requester);
        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/confirmation", [
            'status' => 'accepted',
        ]);

        $this->actingAs($this->itLead);

        $this->postJson("/api/v1/tickets/{$this->ticket->id}/close", [
            'closure_summary' => 'Done',
            'resolution_summary' => 'Resolved',
            'business_outcome' => 'Happy',
        ])->assertStatus(200);

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/close", [
            'closure_summary' => 'Done again',
            'resolution_summary' => 'Resolved',
            'business_outcome' => 'Happy',
        ]);
        $this->assertFalse($response->isSuccessful());
    }
}
