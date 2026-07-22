<?php

namespace Tests\Feature;

use App\Models\NotificationDeliveryLog;
use App\Models\Role;
use App\Models\SlaEscalationPolicy;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketInactivityReminderService;
use Carbon\Carbon;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketInactivityReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    }

    public function test_inactivity_reminder_detects_inactive_and_skips_active_tickets()
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

        $pic = User::factory()->create([
            'role_id' => Role::where('key', 'pic')->firstOrFail()->id,
        ]);

        // Active ticket: updated very recently (1 minute ago) - should NOT trigger
        $activeTicket = Ticket::factory()->create([
            'status' => 'analysis',
            'current_assignee_id' => $pic->id,
        ]);
        \DB::table('tickets')->where('id', $activeTicket->id)->update([
            'updated_at' => Carbon::now()->subMinutes(1)->toDateTimeString(),
        ]);

        // Inactive ticket: updated 2 hours ago - SHOULD trigger
        $inactiveTicket = Ticket::factory()->create([
            'status' => 'development_in_progress',
            'current_assignee_id' => $pic->id,
        ]);
        \DB::table('tickets')->where('id', $inactiveTicket->id)->update([
            'updated_at' => Carbon::now()->subMinutes(120)->toDateTimeString(),
        ]);

        $service = app(TicketInactivityReminderService::class);
        $stats = $service->scanAndAlert();

        // Should alert for the inactive ticket only
        $this->assertEquals(1, $stats['alerts_sent'], 'Should alert for inactive ticket');

        // Run again — deduplication should prevent double alerts
        $stats2 = $service->scanAndAlert();
        $this->assertEquals(0, $stats2['alerts_sent'], 'Deduplication prevents repeat alert');

        $this->assertEquals(1, NotificationDeliveryLog::where('notification_type', 'ticket_inactive')->where('status', 'delivered')->count());
    }
}
