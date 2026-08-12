<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\WhatsAppNotification;
use App\Models\WhatsAppNotificationSetting;
use App\Services\WhatsAppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppOutboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['whatsapp.enabled' => true, 'whatsapp.provider' => 'fonnte', 'whatsapp.fonnte.it_support_number' => '6281234567890']);
    }

    public function test_outbox_is_masked_rendered_queued_and_dispatched_once(): void
    {
        $service = app(WhatsAppNotificationService::class);
        $first = $service->notifyItSupport('test', null, ['time' => '10:00', 'note' => '<b>Aman</b>'], 'same-event');
        $duplicate = $service->notifyItSupport('test', null, ['time' => '10:01', 'note' => 'Duplicate'], 'same-event');

        $this->assertSame($first->id, $duplicate->id);
        $this->assertSame('queued', $first->status);
        $this->assertSame('it_support', $first->recipient_role);
        $this->assertSame('7890', $first->recipient_last_four);
        $this->assertSame(hash('sha256', '6281234567890'), $first->recipient_hash);
        $this->assertStringContainsString('Aman', $first->rendered_message);
        $this->assertDatabaseCount('whatsapp_notifications', 1);
        Queue::assertPushed(SendWhatsAppNotificationJob::class, 1);
    }

    public function test_disabled_and_missing_recipient_do_not_queue(): void
    {
        $service = app(WhatsAppNotificationService::class);
        config(['whatsapp.enabled' => false]);
        $this->assertNull($service->notifyItSupport('test', null, [], 'disabled'));
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.it_support_number' => '']);
        $this->assertNull($service->notifyItSupport('test', null, [], 'missing'));
        $this->assertSame(0, WhatsAppNotification::count());
    }

    public function test_database_overrides_control_effective_global_and_event_settings(): void
    {
        $service = app(WhatsAppNotificationService::class);
        WhatsAppNotificationSetting::create(['enabled' => false, 'events' => ['ticket_created' => true]]);
        $this->assertNull($service->notifyItSupport('ticket_created', null, [], 'global-disabled'));

        WhatsAppNotificationSetting::firstOrFail()->update(['enabled' => true, 'events' => ['ticket_created' => false]]);
        $this->assertNull($service->notifyItSupport('ticket_created', null, [], 'event-disabled'));

        config(['whatsapp.enabled' => false]);
        WhatsAppNotificationSetting::firstOrFail()->update(['enabled' => true, 'events' => ['ticket_created' => true]]);
        $this->assertNull($service->notifyItSupport('ticket_created', null, [
            'ticket_number' => 'TIC-1', 'title' => 'Test', 'requester_name' => 'Pemohon', 'branch' => 'Pusat', 'internal_url' => 'https://example.test',
        ], 'enabled'));
    }
}
