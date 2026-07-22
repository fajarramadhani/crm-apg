<?php

namespace Tests\Feature;

use App\Models\SlaEscalationPolicy;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketSlaAlert;
use App\Models\User;
use App\Services\TicketSlaEscalationService;
use Carbon\CarbonImmutable;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketSlaEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    }

    public function test_sla_scanner_is_idempotent()
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-22 10:00:00', 'Asia/Jakarta'));
        // Setup SLA escalation policy (matches when no priority specified → fallback)
        SlaEscalationPolicy::create([
            'name' => 'Critical Response',
            'sla_type' => 'response',
            'priority' => null, // fallback for any priority
            'warning_threshold_percent' => 50,
            'critical_threshold_percent' => 75,
            'escalate_to_supervisor' => true,
            'escalate_to_it_lead' => false,
            'escalate_to_manager' => false,
            'is_active' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        // Get SLA policy from seeder and ensure it has short deadlines for testing
        $slaPolicy = SlaPolicy::first();
        $slaPolicy->update(['response_minutes' => 60, 'resolution_minutes' => 240]);

        // Ticket in pending_validation (not yet responded) submitted 35 min ago → 58% of 60 min response SLA
        $ticket = Ticket::factory()->create([
            'status' => 'pending_validation',
            'sla_policy_id' => $slaPolicy->id,
            'submitted_at' => CarbonImmutable::now()->subMinutes(35),
        ]);

        $service = app(TicketSlaEscalationService::class);

        // Run scanner once — should detect approaching response SLA
        $stats1 = $service->scanAndAlert();
        $this->assertEquals(1, $stats1['approaching_alerts']);

        // Run scanner again (same thresholds) — should be idempotent (dedup key exists)
        $stats2 = $service->scanAndAlert();
        $this->assertEquals(0, $stats2['approaching_alerts']);
    }

    public function test_sla_resolution_approaching_is_detected()
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-22 10:00:00', 'Asia/Jakarta'));

        // Policy for resolution SLA (no priority filter)
        SlaEscalationPolicy::create([
            'name' => 'Default Resolution',
            'sla_type' => 'resolution',
            'priority' => null,
            'warning_threshold_percent' => 50,
            'critical_threshold_percent' => 75,
            'is_active' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        $slaPolicy = SlaPolicy::first();
        $slaPolicy->update(['response_minutes' => 0, 'resolution_minutes' => 240]); // no response SLA

        // Ticket in analysis (response already met), submitted 125 min ago → 52% of 240 min resolution
        $ticket = Ticket::factory()->create([
            'status' => 'analysis',
            'sla_policy_id' => $slaPolicy->id,
            'submitted_at' => CarbonImmutable::now()->subMinutes(125),
        ]);

        $service = app(TicketSlaEscalationService::class);
        $stats = $service->scanAndAlert();

        // 1 resolution approaching
        $this->assertEquals(1, $stats['approaching_alerts']);
        $this->assertEquals(0, $stats['breach_alerts']);

        $this->assertEquals(1, TicketSlaAlert::count());
        $keys = TicketSlaAlert::pluck('deduplication_key')->toArray();
        $this->assertCount(1, array_unique($keys));
    }
}
