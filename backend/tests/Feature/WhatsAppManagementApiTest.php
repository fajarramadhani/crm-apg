<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppNotification;
use App\Models\WhatsAppNotificationSetting;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Queue::fake();
        Http::preventStrayRequests();
        config(['whatsapp.enabled' => true, 'whatsapp.provider' => 'fonnte', 'whatsapp.fonnte.base_url' => 'https://fonnte.test',
            'whatsapp.fonnte.token' => 'secret-token', 'whatsapp.fonnte.webhook_secret' => 'test-webhook-secret-at-least-32-chars',
            'whatsapp.fonnte.it_support_number' => '6281234567890']);
    }

    public function test_settings_are_masked_and_do_not_expose_secrets(): void
    {
        $response = $this->actingAs($this->admin())->getJson('/api/v1/admin/whatsapp/settings')->assertOk()
            ->assertJsonPath('provider', 'FONNTE')->assertJsonPath('ready', true)
            ->assertJsonPath('it_support_number_masked', '6281******7890')
            ->assertJsonPath('config_defaults.enabled', true)
            ->assertJsonPath('has_database_override', false);
        $this->assertStringNotContainsString('secret-token', $response->getContent());
        $this->assertStringNotContainsString('test-webhook-secret-at-least-32-chars', $response->getContent());
    }

    public function test_admin_can_persist_only_enabled_and_known_event_overrides(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->patchJson('/api/v1/admin/whatsapp/settings', [
            'enabled' => false,
            'events' => ['ticket_created' => false, 'ticket_assigned' => true],
        ])->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('events.ticket_created', false)
            ->assertJsonPath('config_defaults.enabled', true)
            ->assertJsonPath('has_database_override', true);

        $this->assertDatabaseHas('whatsapp_notification_settings', ['enabled' => false]);
        $this->assertFalse(WhatsAppNotificationSetting::firstOrFail()->events['ticket_created']);
        $this->actingAs($admin)->patchJson('/api/v1/admin/whatsapp/settings', [
            'enabled' => true, 'events' => ['arbitrary_event' => true],
        ])->assertUnprocessable()->assertJsonValidationErrors('events');
    }

    public function test_settings_update_requires_management_permission(): void
    {
        $this->patchJson('/api/v1/admin/whatsapp/settings', ['enabled' => false])->assertUnauthorized();
        $user = User::factory()->create(['role_id' => Role::where('key', 'supervisor_it')->firstOrFail()->id, 'is_active' => true]);
        $this->actingAs($user)->patchJson('/api/v1/admin/whatsapp/settings', ['enabled' => false])->assertForbidden();
    }

    public function test_environment_kill_switch_blocks_runtime_enablement(): void
    {
        config(['whatsapp.enabled' => false]);

        $this->actingAs($this->admin())->patchJson('/api/v1/admin/whatsapp/settings', [
            'enabled' => true,
            'events' => [],
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('whatsapp_notification_settings', ['enabled' => true]);
    }

    public function test_effective_disabled_override_blocks_device_and_test_operations(): void
    {
        WhatsAppNotificationSetting::create(['enabled' => false, 'events' => []]);
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/api/v1/admin/whatsapp/device-profile')->assertUnprocessable();
        $this->actingAs($admin)->postJson('/api/v1/admin/whatsapp/test-message')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_device_profile_uses_fonnte_and_history_masks_recipient(): void
    {
        Http::fake(['https://fonnte.test/device' => Http::response(['status' => true, 'device' => 'connected'])]);
        $this->actingAs($this->admin())->getJson('/api/v1/admin/whatsapp/device-profile')->assertOk()->assertJsonPath('success', true);
        $row = $this->row('sent');
        $response = $this->actingAs($this->admin())->getJson('/api/v1/admin/whatsapp/history')->assertOk();
        $this->assertSame('****7890', $response->json('data.0.recipient_masked'));
        $this->assertStringNotContainsString('6281234567890', $response->getContent());
    }

    public function test_test_message_targets_only_configured_it_support_and_retry_queues_failed_row(): void
    {
        $response = $this->actingAs($this->admin())->postJson('/api/v1/admin/whatsapp/test-message', ['target' => '628999999999', 'note' => 'Test'])->assertOk();
        $id = $response->json('data.notification_id');
        $this->assertSame('6281234567890', WhatsAppNotification::findOrFail($id)->recipient_encrypted);
        $failed = $this->row('failed');
        $failed->update(['provider_message_id' => 'old-id', 'provider_message_ids' => ['old-id'], 'failure_code' => 'OLD_FAILURE']);
        $this->actingAs($this->admin())->postJson("/api/v1/admin/whatsapp/history/{$failed->id}/retry")->assertOk()->assertJsonPath('data.status', 'queued');
        $failed->refresh();
        $this->assertNull($failed->provider_message_id);
        $this->assertNull($failed->provider_message_ids);
        $this->assertNull($failed->failure_code);
        $this->assertSame(0, $failed->attempts);
    }

    public function test_unauthorized_users_are_blocked(): void
    {
        $this->getJson('/api/v1/admin/whatsapp/settings')->assertUnauthorized();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('key', 'superadmin')->firstOrFail()->id, 'is_active' => true]);
    }

    private function row(string $status): WhatsAppNotification
    {
        return WhatsAppNotification::create(['event_type' => 'test', 'recipient_hash' => hash('sha256', '6281234567890'),
            'recipient_last_four' => '7890', 'recipient_encrypted' => '6281234567890', 'recipient_role' => 'it_support',
            'recipient_type' => 'configured', 'template_name' => 'test_it_support', 'rendered_message' => 'Test',
            'deduplication_key' => hash('sha256', (string) str()->uuid()), 'provider' => 'fonnte', 'status' => $status]);
    }
}
