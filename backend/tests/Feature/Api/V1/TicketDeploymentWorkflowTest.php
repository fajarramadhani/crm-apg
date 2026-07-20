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
            'estimated_duration_minutes' => 60,
            'affected_components' => ['app'],
            'validation_steps' => ['test'],
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

        $response = $this->postJson("/api/v1/tickets/{$this->ticket->id}/deployments/step", [
            'step_number' => 1,
            'title' => 'Copy files',
            'description' => 'Copy build files to server',
            'status' => 'completed',
        ]);
        $response->assertStatus(200);

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

        $this->assertEquals(TicketStatus::Closed->value, $response->json('ticket.status'));
    }
}
