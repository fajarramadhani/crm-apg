<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketDeployment;
use App\Models\TicketReleasePlan;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportQueryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(MasterDataSeeder::class);
    }

    public function test_ticket_volume_aging_report_computes_via_sql_aggregate(): void
    {
        $manager = User::factory()->create(['role_id' => Role::where('key', 'manager')->firstOrFail()->id, 'division_id' => Division::firstOrFail()->id, 'is_active' => true]);
        Ticket::factory()->create(['division_id' => $manager->division_id, 'submitted_at' => now()->subDays(10), 'closed_at' => null]);
        Ticket::factory()->create(['division_id' => $manager->division_id, 'submitted_at' => now()->subDays(20), 'closed_at' => null]);

        $response = $this->actingAs($manager)->getJson('/api/v1/reports/manager/aging-tickets');
        $response->assertOk()
            ->assertJsonStructure(['data' => ['average_age_days', 'oldest_ticket_days']]);

        $this->assertGreaterThanOrEqual(10.0, $response->json('data.average_age_days'));
        $this->assertEquals(20, $response->json('data.oldest_ticket_days'));
    }

    public function test_deployment_report_summary_computes_via_sql_aggregates(): void
    {
        $manager = User::factory()->create(['role_id' => Role::where('key', 'manager')->firstOrFail()->id, 'division_id' => Division::firstOrFail()->id, 'is_active' => true]);
        $ticket = Ticket::factory()->create(['division_id' => $manager->division_id]);

        $approvalRequest = $ticket->approvalRequests()->create([
            'cycle_number' => 1,
            'requested_by' => $manager->id,
            'requested_at' => now(),
            'request_type' => 'release',
            'status' => 'approved',
            'version' => 1,
            'summary' => 'Fixture',
            'release_risk_level' => 'low',
        ]);

        $plan = TicketReleasePlan::create([
            'ticket_id' => $ticket->id,
            'approval_request_id' => $approvalRequest->id,
            'version' => 1,
            'status' => 'approved',
            'created_by' => $manager->id,
            'release_owner_id' => $manager->id,
            'release_type' => 'standard',
            'target_environment' => 'production',
            'change_summary' => 'Fixture',
            'technical_summary' => 'Fixture',
            'affected_components' => ['backend'],
            'data_migration_required' => false,
            'downtime_required' => false,
            'estimated_duration_minutes' => 30,
            'validation_steps' => ['steps'],
            'monitoring_plan' => ['plan'],
            'lock_version' => 1,
        ]);

        $rollbackPlan = $ticket->rollbackPlans()->create([
            'release_plan_id' => $plan->id,
            'version' => 1,
            'created_by' => $manager->id,
            'rollback_trigger' => 'Failure',
            'rollback_steps' => ['steps'],
            'estimated_rollback_minutes' => 15,
            'validation_after_rollback' => ['steps'],
            'responsible_user_id' => $manager->id,
            'status' => 'approved',
            'lock_version' => 1,
        ]);

        TicketDeployment::create([
            'ticket_id' => $ticket->id,
            'release_plan_id' => $plan->id,
            'rollback_plan_id' => $rollbackPlan->id,
            'cycle_number' => 1,
            'deployment_number' => 1,
            'environment' => 'staging',
            'release_version' => '1.0',
            'status' => 'succeeded',
            'actual_start_at' => now()->subMinutes(60),
            'actual_end_at' => now()->subMinutes(30),
            'deployment_owner_id' => $manager->id,
            'release_owner_id' => $manager->id,
            'approved_by' => $manager->id,
            'deployment_summary' => 'Fixture',
            'version' => 1,
        ]);

        $response = $this->actingAs($manager)->getJson('/api/v1/reports/manager/deployment?date_from='.now()->subDay()->toDateString().'&date_to='.now()->addDay()->toDateString());
        $response->assertOk()
            ->assertJsonPath('data.deployments_succeeded', 1)
            ->assertJsonPath('data.deployments_started', 1);

        $this->assertEquals(100.0, (float) $response->json('data.deployment_success_rate'));
    }
}
