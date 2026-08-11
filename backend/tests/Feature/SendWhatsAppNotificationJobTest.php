<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppGateway;
use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\WhatsAppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SendWhatsAppNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['whatsapp.provider' => 'fonnte', 'whatsapp.fonnte.base_url' => 'https://fonnte.test',
            'whatsapp.fonnte.token' => 'raw-token', 'whatsapp.fonnte.retry_times' => 1, 'whatsapp.queue_tries' => 2]);
    }

    public function test_success_uses_raw_authorization_and_fonnte_message_ids(): void
    {
        Http::fake(['https://fonnte.test/send' => Http::response(['status' => true, 'id' => ['msg-1', 'msg-2'], 'requestid' => 'req-1', 'process' => 'pending'])]);
        $row = $this->row();
        (new SendWhatsAppNotificationJob($row->id))->handle(app(WhatsAppGateway::class));
        $row->refresh();
        $this->assertSame('pending', $row->status);
        $this->assertSame(['msg-1', 'msg-2'], $row->provider_message_ids);
        $this->assertSame('req-1', $row->provider_request_id);
        Http::assertSent(function ($request): bool {
            $fields = collect($request->data())->mapWithKeys(fn (array $field): array => [$field['name'] => $field['contents']]);

            return $request->header('Authorization')[0] === 'raw-token'
                && $request->isMultipart()
                && $fields->get('connectOnly') === 'true'
                && $fields->get('target') === '6281234567890';
        });
    }

    #[DataProvider('permanentFailures')]
    public function test_http_200_provider_failures_are_classified(array $body, string $code, string $status): void
    {
        Http::fake(['https://fonnte.test/send' => Http::response($body, 200)]);
        $row = $this->row();
        (new SendWhatsAppNotificationJob($row->id))->handle(app(WhatsAppGateway::class));
        $row->refresh();
        $this->assertSame($status, $row->status);
        $this->assertSame($code, $row->failure_code);
    }

    public static function permanentFailures(): array
    {
        return [
            'invalid token' => [['status' => false, 'reason' => 'Invalid token'], 'INVALID_TOKEN', 'failed'],
            'invalid number' => [['status' => false, 'reason' => 'Invalid target number'], 'INVALID_NUMBER', 'invalid'],
        ];
    }

    public function test_disconnected_device_is_failed_without_immediate_retry(): void
    {
        Http::fake(['https://fonnte.test/send' => Http::response(['status' => false, 'reason' => 'Device disconnected'], 200)]);
        $row = $this->row();

        (new SendWhatsAppNotificationJob($row->id))->handle(app(WhatsAppGateway::class));

        $this->assertSame('failed', $row->fresh()->status);
        $this->assertSame('DEVICE_DISCONNECTED', $row->fresh()->failure_code);
    }

    public function test_quota_failure_is_failed_without_immediate_retry(): void
    {
        Http::fake(['https://fonnte.test/send' => Http::response(['status' => false, 'reason' => 'Insufficient quota'], 200)]);
        $row = $this->row();

        (new SendWhatsAppNotificationJob($row->id))->handle(app(WhatsAppGateway::class));

        $this->assertSame('failed', $row->fresh()->status);
        $this->assertSame('QUOTA_EXCEEDED', $row->fresh()->failure_code);
    }

    public function test_server_failure_returns_row_to_queue_for_retry(): void
    {
        Http::fake(['https://fonnte.test/send' => Http::response(['status' => false, 'reason' => 'Unavailable'], 503)]);
        $row = $this->row();

        $this->expectException(RuntimeException::class);
        try {
            (new SendWhatsAppNotificationJob($row->id))->handle(app(WhatsAppGateway::class));
        } finally {
            $this->assertSame('queued', $row->fresh()->status);
            $this->assertSame('FONNTE_REJECTED', $row->fresh()->failure_code);
        }
    }

    public function test_duplicate_job_does_not_resend_message_awaiting_webhook(): void
    {
        Http::fake(['https://fonnte.test/send' => Http::response(['status' => true, 'id' => ['msg-1'], 'process' => 'pending'])]);
        $row = $this->row();
        $job = new SendWhatsAppNotificationJob($row->id);

        $job->handle(app(WhatsAppGateway::class));
        $job->handle(app(WhatsAppGateway::class));

        $this->assertSame('pending', $row->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_gateway_does_not_repeat_ambiguous_timeout(): void
    {
        config(['whatsapp.fonnte.retry_times' => 2, 'whatsapp.queue_tries' => 1]);
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('timeout token=raw-token');
        });
        $row = $this->row();
        (new SendWhatsAppNotificationJob($row->id))->handle(app(WhatsAppGateway::class));
        $this->assertSame('failed', $row->fresh()->status);
        $this->assertSame('DELIVERY_STATE_UNKNOWN', $row->fresh()->failure_code);
        $this->assertSame(1, $calls);
    }

    public function test_stale_processing_row_is_failed_without_resending(): void
    {
        Http::fake();
        $row = $this->row();
        $row->update(['status' => 'processing', 'processing_at' => now()->subMinute()]);

        (new SendWhatsAppNotificationJob($row->id))->handle(app(WhatsAppGateway::class));

        $this->assertSame('failed', $row->fresh()->status);
        $this->assertSame('DELIVERY_STATE_UNKNOWN', $row->fresh()->failure_code);
        Http::assertNothingSent();
    }

    private function row(): WhatsAppNotification
    {
        return WhatsAppNotification::create(['event_type' => 'test', 'recipient_hash' => hash('sha256', '6281234567890'),
            'recipient_last_four' => '7890', 'recipient_encrypted' => '6281234567890', 'recipient_role' => 'it_support',
            'recipient_type' => 'configured', 'template_name' => 'test_it_support', 'rendered_message' => 'Pesan aman',
            'deduplication_key' => hash('sha256', (string) str()->uuid()), 'provider' => 'fonnte', 'status' => 'queued', 'attempts' => 0]);
    }
}
