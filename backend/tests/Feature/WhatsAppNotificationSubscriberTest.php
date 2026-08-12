<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Events\TicketAnalysisStarted;
use App\Events\TicketAssigned;
use App\Events\TicketRevisionRequested;
use App\Events\TicketSubmitted;
use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WhatsAppNotification;
use App\Services\PublicTicketTrackingService;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppNotificationSubscriberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        Queue::fake();
        config([
            'whatsapp.enabled' => true,
            'whatsapp.provider' => 'fonnte',
            'whatsapp.fonnte.it_support_number' => '6281234567890',
            'whatsapp.events.ticket_created' => true,
            'whatsapp.events.ticket_assigned' => true,
            'whatsapp.events.important_status_changed' => true,
        ]);
    }

    public function test_ticket_creation_queues_only_requester_when_it_support_recipient_is_disabled(): void
    {
        config(['public_tracking.frontend_url' => 'https://requester.example.test']);
        $ticket = Ticket::factory()->create(['requester_id' => null, 'requester_name' => 'Siti Pemohon',
            'requester_phone' => '085171554545', 'ticket_number' => 'TIC-100', 'status' => TicketStatus::PendingValidation]);
        app(PublicTicketTrackingService::class)->create($ticket);

        event(new TicketSubmitted($ticket));
        event(new TicketSubmitted($ticket));

        $this->assertDatabaseHas('whatsapp_notifications', ['ticket_id' => $ticket->id, 'recipient_role' => 'requester', 'status' => 'queued']);
        $this->assertDatabaseMissing('whatsapp_notifications', ['ticket_id' => $ticket->id, 'recipient_role' => 'it_support']);
        $notification = WhatsAppNotification::where('recipient_role', 'requester')->sole();
        $this->assertSame('public_requester', $notification->recipient_type);
        $this->assertSame('6285171554545', $notification->recipient_encrypted);
        $message = $notification->rendered_message;
        $this->assertStringContainsString('TIC-100', $message);
        $this->assertStringContainsString('https://requester.example.test/track/', $message);
        $this->assertStringContainsString('Pesan ini dikirim otomatis oleh APG CRM.', $message);
        Queue::assertPushed(SendWhatsAppNotificationJob::class, 1);
    }

    public function test_assignment_queues_message_to_pic_phone(): void
    {
        $actor = User::factory()->create();
        $pic = User::factory()->create(['phone' => '081234567890', 'name' => 'Budi PIC']);
        $ticket = Ticket::factory()->create(['ticket_number' => 'TIC-101', 'current_assignee_id' => $pic->id, 'status' => TicketStatus::Assigned]);
        event(new TicketAssigned($ticket, $actor, $pic));
        $row = WhatsAppNotification::where('recipient_role', 'pic')->firstOrFail();
        $this->assertSame('user', $row->recipient_type);
        $this->assertStringContainsString('Budi PIC', $row->rendered_message);
        $this->assertStringContainsString('Prioritas:', $row->rendered_message);
        $this->assertStringContainsString('Pemohon:', $row->rendered_message);
    }

    public function test_actual_processing_and_revision_events_notify_requester_once_each(): void
    {
        $requester = User::factory()->create(['phone' => '085171554545']);
        $actor = User::factory()->create();
        $ticket = Ticket::factory()->create(['requester_id' => $requester->id, 'requester_name' => 'Siti Pemohon',
            'requester_phone' => $requester->phone, 'ticket_number' => 'TIC-102']);

        event(new TicketAnalysisStarted($ticket, $actor));
        event(new TicketAnalysisStarted($ticket, $actor));
        event(new TicketRevisionRequested($ticket));

        $rows = WhatsAppNotification::where('recipient_role', 'requester')->get();
        $this->assertCount(2, $rows);
        $this->assertTrue($rows->contains(fn ($row) => str_contains($row->rendered_message, 'Sedang diproses')));
        $this->assertTrue($rows->contains(fn ($row) => str_contains($row->rendered_message, 'Menunggu informasi atau revisi')));
    }

    public function test_notification_failure_never_breaks_ticket_event(): void
    {
        config(['whatsapp.templates.ticket_created_it_support' => null]);
        $ticket = Ticket::factory()->create(['requester_phone' => null, 'requester_id' => null]);
        event(new TicketSubmitted($ticket));
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
    }
}
