<?php

namespace App\Jobs;

use App\Contracts\WhatsAppGateway;
use App\Models\WhatsAppNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class SendWhatsAppNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $notificationId)
    {
        $this->tries = max(1, (int) config('whatsapp.queue_tries', 2));
        $this->timeout = max(5, (int) config('whatsapp.fonnte.timeout', 15) + 5);
    }

    public function backoff(): array
    {
        return config('whatsapp.retry_backoff_seconds', [60, 300, 900]);
    }

    public function handle(WhatsAppGateway $gateway): void
    {
        $notification = DB::transaction(function (): ?WhatsAppNotification {
            $row = WhatsAppNotification::query()->whereKey($this->notificationId)->lockForUpdate()->first();
            if (! $row || ! in_array($row->status, ['queued', 'failed', 'processing'], true)) {
                return null;
            }
            if ($row->status === 'processing') {
                if ($row->processing_at?->gt(now()->subSeconds($this->timeout + 5))) {
                    return null;
                }
                $row->forceFill([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'failure_code' => 'DELIVERY_STATE_UNKNOWN',
                    'failure_message_safe' => 'Status pengiriman tidak dapat dipastikan setelah proses worker terhenti.',
                ])->save();

                return null;
            }
            if ($row->status === 'failed' && $row->attempts >= $this->tries) {
                return null;
            }
            $row->forceFill(['status' => 'processing', 'processing_at' => now(), 'attempts' => $row->attempts + 1])->save();

            return $row;
        });
        if (! $notification) {
            return;
        }

        $recipient = $notification->recipient_encrypted;
        if (! $recipient) {
            $notification->update(['status' => 'invalid', 'failed_at' => now(), 'failure_code' => 'MISSING_RECIPIENT',
                'failure_message_safe' => 'Nomor penerima tidak tersedia.']);

            return;
        }

        $result = $gateway->sendMessage($recipient, $notification->rendered_message);
        $messageIds = array_values(array_map('strval', $result['message_ids'] ?? []));
        $common = [
            'provider_message_id' => $messageIds[0] ?? null,
            'provider_message_ids' => $messageIds,
            'provider_request_id' => $result['request_id'] ?? null,
            'provider_response' => $result['response'] ?? [],
            'failure_code' => $result['error_code'] ?? null,
            'failure_message_safe' => $result['error_message'] ?? null,
        ];
        if ($result['success'] ?? false) {
            $status = in_array($result['status'] ?? null, ['sent', 'pending'], true) ? $result['status'] : 'pending';
            $notification->update([...$common, 'status' => $status, 'sent_at' => $status === 'sent' ? now() : null]);

            return;
        }

        if (($result['retryable'] ?? false) && $notification->attempts < $this->tries) {
            $notification->update([...$common, 'status' => 'queued', 'processing_at' => null, 'failed_at' => null]);
            throw new RuntimeException('Transient WhatsApp provider failure.');
        }

        $status = ($result['status'] ?? null) === 'invalid' ? 'invalid' : 'failed';
        $notification->update([...$common, 'status' => $status, 'failed_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        WhatsAppNotification::query()->whereKey($this->notificationId)
            ->whereIn('status', ['queued', 'processing', 'pending'])
            ->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_code' => 'QUEUE_ATTEMPTS_EXHAUSTED',
                'failure_message_safe' => 'Pengiriman berhenti setelah batas percobaan antrean tercapai.',
            ]);
    }
}
