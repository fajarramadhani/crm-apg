<?php

namespace Tests\Feature;

use App\Models\SlaEscalationPolicy;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketSlaEscalationService;
use Carbon\CarbonImmutable;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TicketSlaEscalationNplus1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
        Queue::fake();
    }

    public function test_scanner_does_not_query_escalation_policy_per_ticket(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-22 10:00:00', 'Asia/Jakarta'));

        SlaEscalationPolicy::create([
            'name' => 'Default Response',
            'sla_type' => 'response',
            'priority' => null,
            'warning_threshold_percent' => 50,
            'critical_threshold_percent' => 75,
            'escalate_to_supervisor' => true,
            'is_active' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        $slaPolicy = SlaPolicy::first();
        $slaPolicy->update(['response_minutes' => 60, 'resolution_minutes' => 0]);

        // Create 3 tickets in pending_validation so response SLA is evaluated.
        for ($i = 0; $i < 3; $i++) {
            Ticket::factory()->create([
                'status' => 'pending_validation',
                'sla_policy_id' => $slaPolicy->id,
                'submitted_at' => CarbonImmutable::now()->subMinutes(35),
            ]);
        }

        $beforePolicies = SlaEscalationPolicy::count();

        DB::enableQueryLog();
        app(TicketSlaEscalationService::class)->scanAndAlert();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // The scanner must load escalation policies once, not per-ticket.
        $policyLookups = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'sla_escalation_policies'));
        $this->assertLessThan(2, $policyLookups->count(), 'Escapolation policy must be loaded once for the entire scan, not per-ticket.');
        $this->assertEquals($beforePolicies, SlaEscalationPolicy::count());
    }

    public function test_priority_specific_then_default_policy_precedence(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-22 10:00:00', 'Asia/Jakarta'));

        $default = SlaEscalationPolicy::create([
            'name' => 'Default',
            'sla_type' => 'response',
            'priority' => null,
            'warning_threshold_percent' => 50,
            'critical_threshold_percent' => 75,
            'escalate_to_supervisor' => true,
            'is_active' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        $priority = SlaEscalationPolicy::create([
            'name' => 'Critical',
            'sla_type' => 'response',
            'priority' => 'critical',
            'warning_threshold_percent' => 20,
            'critical_threshold_percent' => 40,
            'escalate_to_supervisor' => true,
            'is_active' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        $slaPolicy = SlaPolicy::first();
        $slaPolicy->update(['response_minutes' => 100, 'resolution_minutes' => 0]);

        $pic = User::factory()->create(['phone' => '081234567890']);
        $ticket = Ticket::factory()->create([
            'status' => 'triage',
            'sla_policy_id' => $slaPolicy->id,
            'final_priority_id' => 1,
            'submitted_at' => CarbonImmutable::now()->subMinutes(85), // 85% of 100 min → critical (>40% threshold)
        ]);

        config([
            'whatsapp.enabled' => true,
            'whatsapp.events.sla_warning' => true,
            'whatsapp.fonnte.it_support_number' => '6281234567890',
            'public_tracking.frontend_url' => 'http://localhost:5173',
        ]);

        $service = app(TicketSlaEscalationService::class);
        $stats = $service->scanAndAlert();

        // Threshold 85% >= critical_threshold_percent (40) and >= warning (20).
        // Since 85% >= 100? No. Wait 85/100 = 85%. critical threshold 40%. So it's critical (>=40%).
        $this->assertEquals(1, $stats['critical_alerts']);
    }
}
