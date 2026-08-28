<?php

namespace Tests\Feature;

use App\Models\NotificationDeliveryLog;
use App\Models\Role;
use App\Models\SlaEscalationPolicy;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\User;
use App\Services\TicketInactivityReminderService;
use Carbon\Carbon;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketInactivityReminderNplus1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    }

    public function test_inactivity_scanner_preloads_policies_and_history_once_per_scan(): void
    {
        SlaEscalationPolicy::create([
            'name' => 'Default Policy',
            'sla_type' => 'resolution',
            'priority' => null,
            'warning_threshold_percent' => 50,
            'critical_threshold_percent' => 75,
            'inactivity_threshold_minutes' => 60,
            'is_active' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        $picRole = Role::query()->where('key', 'pic')->firstOrFail();
        $pic = User::factory()->create(['role_id' => $picRole->id]);

        // 3 inactive tickets (updated 2h ago) — all should trigger a single batch history query.
        foreach (range(1, 3) as $i) {
            Ticket::factory()->create([
                'status' => 'development_in_progress',
                'current_assignee_id' => $pic->id,
            ]);
            DB::table('tickets')->where('id', Ticket::latest('id')->first()->id)->update([
                'updated_at' => Carbon::now()->subMinutes(120)->toDateTimeString(),
            ]);
        }

        DB::enableQueryLog();
        $stats = app(TicketInactivityReminderService::class)->scanAndAlert();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $historyLookups = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'ticket_status_histories'));
        $policyLookups = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'sla_escalation_policies'));

        $this->assertEquals(3, $stats['alerts_sent']);
        // Policy and history must each be loaded exactly once for the whole scan, not per ticket.
        $this->assertSame(1, $policyLookups->count(), 'Escalation policies must be preloaded once per scan.');
        $this->assertSame(1, $historyLookups->count(), 'Status histories must be batch-loaded once per chunk, not per ticket.');
    }

    public function test_priority_specific_then_default_inactivity_policy_precedence(): void
    {
        $default = SlaEscalationPolicy::create([
            'name' => 'Default Inactivity',
            'sla_type' => 'resolution',
            'priority' => null,
            'inactivity_threshold_minutes' => 60,
            'is_active' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        $priority = SlaEscalationPolicy::create([
            'name' => 'Critical Inactivity',
            'sla_type' => 'resolution',
            'priority' => 'critical',
            'inactivity_threshold_minutes' => 10,
            'is_active' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        $picRole = Role::query()->where('key', 'pic')->firstOrFail();
        $pic = User::factory()->create(['role_id' => $picRole->id]);

        // Ticket with critical priority, inactive 30 min → exceeds the 10 min critical threshold.
        $ticket = Ticket::factory()->create([
            'status' => 'analysis',
            'current_assignee_id' => $pic->id,
            'final_priority_id' => TicketPriority::query()->where('key', 'critical')->firstOrFail()->id,
        ]);
        DB::table('tickets')->where('id', $ticket->id)->update([
            'updated_at' => Carbon::now()->subMinutes(30)->toDateTimeString(),
        ]);

        $stats = app(TicketInactivityReminderService::class)->scanAndAlert();

        $this->assertEquals(1, $stats['alerts_sent']);
        $this->assertEquals(1, NotificationDeliveryLog::where('notification_type', 'ticket_inactive')->where('status', 'delivered')->count());
    }
}
