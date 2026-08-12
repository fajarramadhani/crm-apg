<?php

namespace App\Services;

use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\Ticket;
use App\Models\WhatsAppNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class WhatsAppNotificationService
{
    public function __construct(private readonly WhatsAppNotificationSettings $settings) {}

    public static function normalizePhoneNumber(?string $number): ?string
    {
        $cleaned = preg_replace('/\D+/', '', trim((string) $number)) ?? '';
        if ($cleaned === '') {
            return null;
        }
        if (str_starts_with($cleaned, '00')) {
            $cleaned = substr($cleaned, 2);
        }
        if (str_starts_with($cleaned, '0')) {
            $cleaned = (string) config('whatsapp.fonnte.country_code', '62').substr($cleaned, 1);
        }
        if (! preg_match('/^62[1-9]\d{7,12}$/', $cleaned)) {
            return null;
        }

        return $cleaned;
    }

    public static function maskPhoneNumber(?string $number): string
    {
        $normalized = self::normalizePhoneNumber($number) ?? preg_replace('/\D+/', '', (string) $number) ?? '';
        if (strlen($normalized) < 8) {
            return str_repeat('*', strlen($normalized));
        }

        return substr($normalized, 0, 4).'******'.substr($normalized, -4);
    }

    public static function sanitizeText(string $text, int $maxLength = 500): string
    {
        $text = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', strip_tags($text)) ?? '');

        return mb_strlen($text) > $maxLength ? mb_substr($text, 0, $maxLength - 3).'...' : $text;
    }

    public function queue(
        string $eventType,
        ?Ticket $ticket,
        ?string $recipient,
        string $recipientRole,
        string $recipientType,
        string $templateName,
        array $variables,
        string $deduplicationKey
    ): ?WhatsAppNotification {
        if (! $this->settings->enabled() || ! $this->settings->eventEnabled($eventType)) {
            return null;
        }

        $normalized = self::normalizePhoneNumber($recipient);
        if (! $normalized) {
            Log::notice('WhatsApp notification skipped: recipient is missing or invalid.', [
                'event_type' => $eventType, 'ticket_id' => $ticket?->id, 'recipient_role' => $recipientRole,
            ]);

            return null;
        }

        $template = (string) config('whatsapp.templates.'.$templateName, '');
        if ($template === '') {
            return null;
        }
        $rendered = preg_replace_callback('/\{([a-z_]+)\}/', fn (array $match): string => self::sanitizeText((string) ($variables[$match[1]] ?? '')), $template) ?? '';

        try {
            $notification = WhatsAppNotification::query()->createOrFirst(
                ['deduplication_key' => hash('sha256', $deduplicationKey)],
                [
                    'event_type' => $eventType,
                    'ticket_id' => $ticket?->id,
                    'recipient_hash' => hash('sha256', $normalized),
                    'recipient_last_four' => substr($normalized, -4),
                    'recipient_encrypted' => $normalized,
                    'recipient_role' => $recipientRole,
                    'recipient_type' => $recipientType,
                    'template_name' => $templateName,
                    'rendered_message' => $rendered,
                    'provider' => (string) config('whatsapp.provider', 'fonnte'),
                    'status' => 'queued',
                    'attempts' => 0,
                    'queued_at' => now(),
                ]
            );
        } catch (QueryException $exception) {
            $notification = WhatsAppNotification::query()->where('deduplication_key', hash('sha256', $deduplicationKey))->first();
            if (! $notification) {
                throw $exception;
            }
        }

        if ($notification->wasRecentlyCreated) {
            DB::afterCommit(function () use ($notification): void {
                try {
                    SendWhatsAppNotificationJob::dispatch($notification->id)->onQueue((string) config('whatsapp.queue'));
                } catch (Throwable $exception) {
                    WhatsAppNotification::query()->whereKey($notification->id)->where('status', 'queued')->update([
                        'status' => 'failed',
                        'failed_at' => now(),
                        'failure_code' => 'QUEUE_DISPATCH_FAILED',
                        'failure_message_safe' => 'Pesan gagal dimasukkan ke antrean pengiriman.',
                    ]);
                    Log::error('WhatsApp queue dispatch failed without affecting ticket processing.', [
                        'notification_id' => $notification->id,
                        'exception_class' => $exception::class,
                    ]);
                }
            });
        }

        return $notification;
    }

    public function queueSafely(...$arguments): ?WhatsAppNotification
    {
        try {
            return $this->queue(...$arguments);
        } catch (Throwable $exception) {
            Log::error('WhatsApp outbox creation failed without affecting ticket processing.', [
                'exception_class' => $exception::class,
            ]);

            return null;
        }
    }

    public function notifyItSupport(string $eventType, ?Ticket $ticket, array $variables, ?string $deduplicationKey = null): ?WhatsAppNotification
    {
        $template = str_starts_with($eventType, 'sla_') ? 'sla_recipient' : ($eventType === 'test' ? 'test_it_support' : 'ticket_created_it_support');

        return $this->queueSafely($eventType, $ticket, config('whatsapp.fonnte.it_support_number'), 'it_support', 'configured',
            $template, $variables, $deduplicationKey ?? "{$eventType}:{$ticket?->id}:it-support");
    }
}
