<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketDeployment;
use App\Models\TicketReleasePlan;
use App\Models\TicketRollbackPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportCalculationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Division $division;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(MasterDataSeeder::class);

        $this->division = Division::where('code', 'IT')->firstOrFail();
        $this->manager = User::factory()->create([
            'role_id' => Role::where('key', 'manager')->firstOrFail()->id,
            'division_id' => $this->division->id,
            'is_active' => true,
        ]);
    }

    public function test_sla_report_calculates_compliance_and_elapsed_time_metrics(): void
    {
        CarbonImmutable::setTestNow('2026-08-24 12:00:00');

        $slaPolicy = SlaPolicy::query()->where('is_active', true)->firstOrFail();
        Ticket::factory()->create([
            'division_id' => $this->division->id,
            'sla_policy_id' => $slaPolicy->id,
            'submitted_at' => CarbonImmutable::now()->subHours(4),
            'triage_started_at' => CarbonImmutable::now()->subHours(3),
            'response_due_at' => CarbonImmutable::now()->subHours(2),
            'resolution_due_at' => CarbonImmutable::now()->addHours(1),
            'closed_at' => CarbonImmutable::now()->subHour(),
        ]);
        Ticket::factory()->create([
            'division_id' => $this->division->id,
            'sla_policy_id' => $slaPolicy->id,
            'submitted_at' => CarbonImmutable::now()->subHours(5),
            'triage_started_at' => CarbonImmutable::now()->subHours(2),
            'response_due_at' => CarbonImmutable::now()->subHours(3),
            'resolution_due_at' => CarbonImmutable::now()->subHour(),
            'closed_at' => CarbonImmutable::now(),
        ]);
        Ticket::factory()->create([
            'division_id' => $this->division->id,
            'sla_policy_id' => $slaPolicy->id,
            'submitted_at' => CarbonImmutable::now()->subHours(6),
            'response_due_at' => CarbonImmutable::now()->subHour(),
            'resolution_due_at' => CarbonImmutable::now()->addHour(),
        ]);
        Ticket::factory()->create([
            'division_id' => $this->division->id,
            'sla_policy_id' => null,
            'submitted_at' => CarbonImmutable::now()->subHour(),
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/reports/manager/sla')
            ->assertOk();

        $this->assertSame(3, $response->json('data.total_tickets_with_sla'));
        $this->assertSame(1, $response->json('data.tickets_without_sla'));
        $this->assertSame(3, $response->json('data.response_eligible'));
        $this->assertSame(1, $response->json('data.response_met'));
        $this->assertSame(2, $response->json('data.resolution_eligible'));
        $this->assertSame(1, $response->json('data.resolution_met'));
        $this->assertSame(33.33, (float) $response->json('data.response_compliance_percentage'));
        $this->assertSame(50.0, (float) $response->json('data.resolution_compliance_percentage'));
        $this->assertSame(120.0, (float) $response->json('data.average_response_time_minutes'));
        $this->assertSame(120.0, (float) $response->json('data.median_response_time_minutes'));
        $this->assertSame(240.0, (float) $response->json('data.average_resolution_time_minutes'));
        $this->assertSame(240.0, (float) $response->json('data.median_resolution_time_minutes'));
    }

    private function createReleasePlan(Ticket $ticket): array
    {
        $approvalRequest = $ticket->approvalRequests()->create([
            'cycle_number' => 1,
            'requested_by' => $this->manager->id,
            'requested_at' => CarbonImmutable::now(),
            'request_type' => 'release',
            'status' => 'approved',
            'version' => 1,
            'summary' => 'Report calculation fixture',
            'release_risk_level' => 'low',
        ]);
        $releasePlan = TicketReleasePlan::query()->create([
            'ticket_id' => $ticket->id,
            'approval_request_id' => $approvalRequest->id,
            'version' => 1,
            'created_by' => $this->manager->id,
            'release_owner_id' => $this->manager->id,
            'release_type' => 'standard',
            'target_environment' => 'production',
            'change_summary' => 'Report calculation fixture',
            'technical_summary' => 'Report calculation fixture',
            'affected_components' => ['backend'],
            'data_migration_required' => false,
            'downtime_required' => false,
            'estimated_duration_minutes' => 30,
            'validation_steps' => ['health check'],
            'monitoring_plan' => ['observe metrics'],
            'status' => 'approved',
            'lock_version' => 1,
        ]);
        $rollbackPlan = TicketRollbackPlan::query()->create([
            'ticket_id' => $ticket->id,
            'release_plan_id' => $releasePlan->id,
            'version' => 1,
            'created_by' => $this->manager->id,
            'rollback_trigger' => 'Failed validation',
            'rollback_steps' => ['restore release'],
            'estimated_rollback_minutes' => 15,
            'validation_after_rollback' => ['health check'],
            'responsible_user_id' => $this->manager->id,
            'status' => 'approved',
            'lock_version' => 1,
        ]);

        return [$releasePlan, $rollbackPlan];
    }

    public function test_sla_report_handles_empty_dataset_gracefully(): void
    {
        CarbonImmutable::setTestNow('2026-08-24 12:00:00');

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/reports/manager/sla?date_from=2026-08-01&date_to=2026-08-25')
            ->assertOk();

        $this->assertSame(0, $response->json('data.total_tickets_with_sla'));
        $this->assertNull($response->json('data.average_response_time_minutes'));
        $this->assertNull($response->json('data.median_response_time_minutes'));
        $this->assertNull($response->json('data.compliance_percentage'));
    }

    public function test_ticket_volume_aging_report_handles_empty_dataset(): void
    {
        CarbonImmutable::setTestNow('2026-08-24 12:00:00');

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/reports/manager/aging-tickets?date_from=2026-08-01&date_to=2026-08-25')
            ->assertOk();

        $this->assertNull($response->json('data.average_age_days'));
        $this->assertNull($response->json('data.oldest_ticket_days'));
    }

    public function test_deployment_report_handles_empty_dataset_gracefully(): void
    {
        CarbonImmutable::setTestNow('2026-08-24 12:00:00');

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/reports/manager/deployment?date_from=2026-08-01&date_to=2026-08-25')
            ->assertOk();

        $this->assertSame(0, $response->json('data.deployments_scheduled'));
        $this->assertSame(0, $response->json('data.deployments_started'));
        $this->assertSame(0, $response->json('data.deployments_succeeded'));
        $this->assertSame(0, $response->json('data.deployments_failed'));
        $this->assertNull($response->json('data.average_deployment_duration_minutes'));
        $this->assertNull($response->json('data.median_deployment_duration_minutes'));
    }

    public function test_ticket_volume_aging_calculates_average_and_oldest_days(): void
    {
        CarbonImmutable::setTestNow('2026-08-24 12:00:00');

        Ticket::factory()->create([
            'division_id' => $this->division->id,
            'submitted_at' => CarbonImmutable::now()->subDays(10),
            'closed_at' => null,
        ]);

        Ticket::factory()->create([
            'division_id' => $this->division->id,
            'submitted_at' => CarbonImmutable::now()->subDays(20),
            'closed_at' => null,
        ]);

        Ticket::factory()->create([
            'division_id' => $this->division->id,
            'submitted_at' => CarbonImmutable::now()->subDays(5),
            'closed_at' => CarbonImmutable::now()->subDays(1),
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/reports/manager/aging-tickets')
            ->assertOk();

        $this->assertSame(15.0, (float) $response->json('data.average_age_days'));
        $this->assertSame(20.0, (float) $response->json('data.oldest_ticket_days'));
    }

    public function test_deployment_report_calculates_summary_counts_duration_and_median(): void
    {
        CarbonImmutable::setTestNow('2026-08-24 12:00:00');

        $ticket = Ticket::factory()->create(['division_id' => $this->division->id]);
        [$releasePlan, $rollbackPlan] = $this->createReleasePlan($ticket);
        $this->createDeployments($ticket, $releasePlan, $rollbackPlan);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/reports/manager/deployment?date_from=2026-08-01&date_to=2026-08-25')
            ->assertOk();

        $this->assertSame(1, $response->json('data.deployments_scheduled'));
        $this->assertSame(3, $response->json('data.deployments_started'));
        $this->assertSame(2, $response->json('data.deployments_succeeded'));
        $this->assertSame(1, $response->json('data.deployments_failed'));
        $this->assertSame(66.67, (float) $response->json('data.deployment_success_rate'));
        $this->assertSame(33.33, (float) $response->json('data.deployment_failure_rate'));
        $this->assertSame(120.0, (float) $response->json('data.average_deployment_duration_minutes'));
        $this->assertSame(120.0, (float) $response->json('data.median_deployment_duration_minutes'));
    }

    private function createDeployments(Ticket $ticket, $releasePlan, $rollbackPlan): void
    {
        TicketDeployment::create([
            'ticket_id' => $ticket->id,
            'release_plan_id' => $releasePlan->id,
            'rollback_plan_id' => $rollbackPlan->id,
            'cycle_number' => 1,
            'deployment_number' => 'DEP-01',
            'environment' => 'staging',
            'release_version' => '1.0',
            'created_at' => CarbonImmutable::now()->subDays(1),
            'actual_start_at' => CarbonImmutable::now()->subHours(2),
            'actual_end_at' => CarbonImmutable::now()->subHours(1), // 60 minutes
            'deployment_owner_id' => $this->manager->id,
            'release_owner_id' => $this->manager->id,
            'approved_by' => $this->manager->id,
            'deployment_summary' => 'Test deployment',
            'status' => 'succeeded',
            'version' => 1,
        ]);
        TicketDeployment::create([
            'ticket_id' => $ticket->id,
            'release_plan_id' => $releasePlan->id,
            'rollback_plan_id' => $rollbackPlan->id,
            'cycle_number' => 2,
            'deployment_number' => 'DEP-02',
            'environment' => 'staging',
            'release_version' => '1.1',
            'created_at' => CarbonImmutable::now()->subDays(1),
            'actual_start_at' => CarbonImmutable::now()->subHours(3),
            'actual_end_at' => CarbonImmutable::now()->subHours(1), // 120 minutes
            'deployment_owner_id' => $this->manager->id,
            'release_owner_id' => $this->manager->id,
            'approved_by' => $this->manager->id,
            'deployment_summary' => 'Test deployment 2',
            'status' => 'succeeded',
            'version' => 1,
        ]);
        TicketDeployment::create([
            'ticket_id' => $ticket->id,
            'release_plan_id' => $releasePlan->id,
            'rollback_plan_id' => $rollbackPlan->id,
            'cycle_number' => 3,
            'deployment_number' => 'DEP-03',
            'environment' => 'staging',
            'release_version' => '1.2',
            'created_at' => CarbonImmutable::now()->subDays(1),
            'actual_start_at' => CarbonImmutable::now()->subHours(4),
            'actual_end_at' => CarbonImmutable::now()->subHours(1), // 180 minutes
            'deployment_owner_id' => $this->manager->id,
            'release_owner_id' => $this->manager->id,
            'approved_by' => $this->manager->id,
            'deployment_summary' => 'Test deployment 3',
            'failure_reason' => 'Build failed',
            'status' => 'failed',
            'version' => 1,
        ]);
        TicketDeployment::create([
            'ticket_id' => $ticket->id,
            'release_plan_id' => $releasePlan->id,
            'rollback_plan_id' => $rollbackPlan->id,
            'cycle_number' => 4,
            'deployment_number' => 'DEP-04',
            'environment' => 'production',
            'release_version' => '2.0',
            'created_at' => CarbonImmutable::now()->subDays(1),
            'deployment_owner_id' => $this->manager->id,
            'release_owner_id' => $this->manager->id,
            'approved_by' => $this->manager->id,
            'deployment_summary' => 'Scheduled deployment',
            'status' => 'scheduled',
            'version' => 1,
        ]);
    }
}
