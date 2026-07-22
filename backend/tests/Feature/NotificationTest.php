<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketNotificationRecipientResolver;
use App\Services\TicketNotificationService;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    }

    public function test_user_can_view_own_notifications()
    {
        $user = User::factory()->create();
        $notification = new Notification([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\TicketAlertNotification',
            'data' => ['type' => 'ticket_submitted', 'severity' => 'info', 'title' => 'Test', 'message' => 'Test message'],
        ]);
        $user->notifications()->save($notification);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $notification->id);
    }

    public function test_user_cannot_view_others_notifications()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $notification = new Notification([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\TicketAlertNotification',
            'data' => ['type' => 'ticket_submitted', 'severity' => 'info'],
        ]);
        $user2->notifications()->save($notification);

        $response = $this->actingAs($user1)->getJson("/api/v1/notifications/{$notification->id}");

        $response->assertStatus(404);
    }

    public function test_user_can_get_unread_count()
    {
        $user = User::factory()->create();
        $notification = new Notification([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\TicketAlertNotification',
            'data' => ['type' => 'ticket_submitted'],
        ]);
        $user->notifications()->save($notification);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications/unread-count');
        $response->assertStatus(200)->assertJson(['count' => 1]);
    }

    public function test_user_can_mark_notification_as_read()
    {
        $user = User::factory()->create(['role_id' => 1]);
        $notification = new Notification([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\TicketAlertNotification',
            'data' => ['type' => 'ticket_submitted'],
        ]);
        $user->notifications()->save($notification);

        $response = $this->actingAs($user)->postJson("/api/v1/notifications/{$notification->id}/read");
        $response->assertStatus(200);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_archive_notification_as_soft_hide()
    {
        $user = User::factory()->create(['role_id' => 1]);
        $notification = new Notification([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\TicketAlertNotification',
            'data' => ['type' => 'ticket_submitted'],
        ]);
        $user->notifications()->save($notification);

        $response = $this->actingAs($user)->postJson("/api/v1/notifications/{$notification->id}/archive");
        $response->assertStatus(200);

        $this->assertNotNull($notification->fresh()->archived_at);

        // It should be hidden from index
        $indexResponse = $this->actingAs($user)->getJson('/api/v1/notifications');
        $indexResponse->assertJsonCount(0, 'data');
    }

    public function test_recipient_resolver_filters_inactive_and_executive()
    {
        $resolver = app(TicketNotificationRecipientResolver::class);
        $ticket = Ticket::factory()->create();

        // Create inactive PIC
        $inactivePic = User::factory()->create([
            'role_id' => Role::where('key', 'pic')->firstOrFail()->id,
            'is_active' => false,
        ]);
        $ticket->current_assignee_id = $inactivePic->id;
        $ticket->save();

        $recipients = $resolver->resolve($ticket, 'pic_assigned');
        $this->assertCount(0, $recipients, 'Inactive PIC should be skipped');

        // Create Executive PIC (Executives should be filtered out)
        $executiveUser = User::factory()->create([
            'role_id' => Role::where('key', 'executive')->firstOrFail()->id,
            'is_active' => true,
        ]);
        $ticket->current_assignee_id = $executiveUser->id;
        $ticket->save();

        $recipients2 = $resolver->resolve($ticket, 'pic_assigned');
        $this->assertCount(0, $recipients2, 'Executive PIC should be skipped');
    }

    public function test_payload_redacts_sensitive_keys()
    {
        $service = app(TicketNotificationService::class);
        $ticket = Ticket::factory()->create();
        $pic = User::factory()->create([
            'role_id' => Role::where('key', 'pic')->firstOrFail()->id,
        ]);
        $ticket->current_assignee_id = $pic->id;
        $ticket->save();

        $service->dispatch(
            ticket: $ticket,
            type: 'pic_assigned',
            severity: 'info',
            title: 'Test Title',
            message: 'Test Message',
            metadata: ['password' => 'secret123', 'token' => 'jwt_token', 'safe_key' => 'allowed_value']
        );

        $notification = $pic->notifications()->first();
        $this->assertNotNull($notification);
        $data = $notification->data;

        $this->assertArrayNotHasKey('password', $data['metadata']);
        $this->assertArrayNotHasKey('token', $data['metadata']);
        $this->assertEquals('allowed_value', $data['metadata']['safe_key']);
    }

    public function test_action_url_validation()
    {
        $service = app(TicketNotificationService::class);
        $ticket = Ticket::factory()->create();
        $pic = User::factory()->create([
            'role_id' => Role::where('key', 'pic')->firstOrFail()->id,
        ]);
        $ticket->current_assignee_id = $pic->id;
        $ticket->save();

        // 1. External URL should be nullified
        $service->dispatch(
            ticket: $ticket,
            type: 'pic_assigned',
            severity: 'info',
            title: 'Test',
            message: 'Test',
            actionUrl: 'https://external.com/tickets/123',
            deduplicationRef: 'url_test_1'
        );

        $notification1 = $pic->notifications()->latest()->first();
        $this->assertNull($notification1->data['action_url'], 'External action URL should be nullified');

        // 2. Relative URL should be preserved
        $service->dispatch(
            ticket: $ticket,
            type: 'pic_assigned',
            severity: 'info',
            title: 'Test',
            message: 'Test',
            actionUrl: '/tickets/123',
            deduplicationRef: 'url_test_2'
        );

        $notifications = $pic->notifications()->get();
        $this->assertCount(2, $notifications);

        $externalUrlNotification = $notifications->first(fn ($n) => ! str_contains($n->data['action_url'] ?? '', '/tickets/123'));
        $relativeUrlNotification = $notifications->first(fn ($n) => ($n->data['action_url'] ?? '') === '/tickets/123');

        $this->assertNotNull($externalUrlNotification);
        $this->assertNull($externalUrlNotification->data['action_url'], 'External action URL should be nullified');

        $this->assertNotNull($relativeUrlNotification);
        $this->assertEquals('/tickets/123', $relativeUrlNotification->data['action_url'], 'Internal relative action URL should be allowed');
    }
}
