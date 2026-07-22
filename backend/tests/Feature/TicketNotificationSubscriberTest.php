<?php

namespace Tests\Feature;

use App\Events\TicketSubmitted;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketNotificationService;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TicketNotificationSubscriberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    }

    public function test_ticket_submitted_event_triggers_notifications_and_logs_delivery()
    {
        $requesterRole = Role::firstOrCreate(['key' => 'requester', 'name' => 'Requester']);
        $supervisorRole = Role::firstOrCreate(['key' => 'supervisor', 'name' => 'Supervisor']);

        $requester = User::factory()->create(['role_id' => $requesterRole->id]); // Requester
        $supervisor = User::factory()->create(['role_id' => $supervisorRole->id, 'division_id' => $requester->division_id]); // Supervisor

        $ticket = Ticket::factory()->create(['requester_id' => $requester->id]);

        // Manually dispatch the event
        event(new TicketSubmitted($ticket));

        // Wait, the notifications are stored in DB
        // Check requester notification
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $requester->id,
            'notifiable_type' => User::class,
        ]);

        // Check supervisor notification
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $supervisor->id,
            'notifiable_type' => User::class,
        ]);

        // Check delivery logs
        $this->assertDatabaseHas('notification_delivery_logs', [
            'user_id' => $requester->id,
            'notification_type' => 'ticket_submitted',
            'status' => 'delivered',
        ]);

        $this->assertDatabaseHas('notification_delivery_logs', [
            'user_id' => $supervisor->id,
            'notification_type' => 'supervisor_validation_required',
            'status' => 'delivered',
        ]);
    }

    public function test_external_url_is_redacted_from_notification()
    {
        $requester = User::factory()->create();
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id]);

        // The subscriber defines the URL internally, but if we call dispatch manually:
        $service = app(TicketNotificationService::class);
        $service->dispatch($ticket, 'ticket_submitted', 'info', 'Test', 'Test', 'https://google.com', null, ['.env' => 'secret']);

        $notification = $requester->notifications()->latest()->first();

        $data = $notification->data;
        $this->assertNull($data['action_url']);
        $this->assertArrayNotHasKey('.env', $data['metadata']);
    }
}
