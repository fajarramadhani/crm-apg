<?php

namespace Tests\Feature;

use App\Models\WhatsAppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['whatsapp.fonnte.webhook_secret' => 'test-webhook-secret-at-least-32-chars']);
    }

    public function test_webhook_requires_secret_and_strict_payload(): void
    {
        $this->postJson('/api/webhooks/fonnte/message-status', [])->assertUnauthorized();
        $this->withHeader('X-Fonnte-Webhook-Secret', 'test-webhook-secret-at-least-32-chars')
            ->postJson('/api/webhooks/fonnte/message-status', ['id' => 'msg'])->assertUnprocessable();
    }

    public function test_webhook_does_not_accept_secret_in_url(): void
    {
        $this->postJson('/api/webhooks/fonnte/message-status/test-webhook-secret-at-least-32-chars', ['id' => 'msg'])
            ->assertNotFound();
    }

    public function test_webhook_accepts_documented_payload_without_timestamp(): void
    {
        $row = $this->row('pending');
        $payload = ['id' => 'msg-1', 'status' => 'Sent', 'state' => 'SENT', 'stateid' => '2', 'device' => 'device-1'];

        $this->withHeader('X-Fonnte-Webhook-Secret', 'test-webhook-secret-at-least-32-chars')
            ->postJson('/api/webhooks/fonnte/message-status', $payload)
            ->assertOk();

        $this->assertSame('sent', $row->fresh()->status);
        $this->assertNotNull($row->fresh()->provider_response['received_at']);
    }

    public function test_webhook_updates_idempotently_and_does_not_regress_terminal_status(): void
    {
        $row = $this->row('pending');
        $payload = ['id' => 'msg-1', 'status' => 'sent', 'state' => 'SENT', 'stateid' => '2', 'device' => 'device-1', 'timestamp' => time()];
        $this->withHeader('X-Fonnte-Webhook-Secret', 'test-webhook-secret-at-least-32-chars')->postJson('/api/webhooks/fonnte/message-status', $payload)->assertOk();
        $this->withHeader('X-Fonnte-Webhook-Secret', 'test-webhook-secret-at-least-32-chars')->postJson('/api/v1/webhooks/fonnte/message-status', $payload)->assertOk();
        $this->assertSame('sent', $row->fresh()->status);
        $this->assertNotNull($row->fresh()->sent_at);

        $failed = [...$payload, 'status' => 'failed', 'state' => 'FAILED'];
        $this->withHeader('X-Fonnte-Webhook-Secret', 'test-webhook-secret-at-least-32-chars')->postJson('/api/webhooks/fonnte/message-status', $failed)->assertOk();
        $this->assertSame('sent', $row->fresh()->status);
    }

    public function test_webhook_normalizes_documented_fonnte_status_values(): void
    {
        $row = $this->row('pending');
        $payload = ['id' => 'msg-1', 'status' => 'Processing', 'state' => 'PROCESSING', 'stateid' => '1', 'device' => 'device-1', 'timestamp' => time()];

        $this->withHeader('X-Fonnte-Webhook-Secret', 'test-webhook-secret-at-least-32-chars')
            ->postJson('/api/webhooks/fonnte/message-status', $payload)
            ->assertOk();
        $this->assertSame('pending', $row->fresh()->status);

        $payload['status'] = 'Sent';
        $payload['state'] = 'SENT';
        $this->withHeader('X-Fonnte-Webhook-Secret', 'test-webhook-secret-at-least-32-chars')
            ->postJson('/api/webhooks/fonnte/message-status', $payload)
            ->assertOk();
        $this->assertSame('sent', $row->fresh()->status);
    }

    public function test_webhook_rejects_malformed_json_and_reports_unknown_message_id(): void
    {
        $this->call('POST', '/api/webhooks/fonnte/message-status', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FONNTE_WEBHOOK_SECRET' => 'test-webhook-secret-at-least-32-chars',
        ], '{')
            ->assertBadRequest();

        $payload = ['id' => 'unknown', 'status' => 'sent', 'state' => 'SENT', 'stateid' => '2', 'device' => 'device-1'];
        $this->withHeader('X-Fonnte-Webhook-Secret', 'test-webhook-secret-at-least-32-chars')
            ->postJson('/api/webhooks/fonnte/message-status', $payload)
            ->assertServiceUnavailable()
            ->assertJsonPath('matched', false);
    }

    private function row(string $status): WhatsAppNotification
    {
        return WhatsAppNotification::create(['event_type' => 'test', 'recipient_hash' => hash('sha256', '6281234567890'),
            'recipient_last_four' => '7890', 'recipient_encrypted' => '6281234567890', 'recipient_role' => 'it_support',
            'recipient_type' => 'configured', 'template_name' => 'test_it_support', 'rendered_message' => 'Test',
            'deduplication_key' => hash('sha256', 'webhook-test'), 'provider' => 'fonnte', 'provider_message_id' => 'msg-1',
            'provider_message_ids' => ['msg-1'], 'status' => $status]);
    }
}
