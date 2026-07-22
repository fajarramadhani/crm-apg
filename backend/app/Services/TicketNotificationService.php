<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\Ticket;
use App\Notifications\TicketAlertNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketNotificationService
{
    public function __construct(
        private TicketNotificationRecipientResolver $resolver,
        private NotificationDeduplicationService $deduplication
    ) {}

    public function dispatch(
        Ticket $ticket,
        string $type,
        string $severity,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?int $actorId = null,
        array $metadata = [],
        ?string $deduplicationRef = null,
        ?int $deduplicationWindow = null
    ): int {
        $sentCount = 0;
        try {
            $recipients = $this->resolver->resolve($ticket, $type);

            foreach ($recipients as $recipient) {
                // Check preferences
                if (! $this->shouldSendToUser($recipient->id, $type, $severity)) {
                    continue;
                }

                $deduplicationKey = $this->deduplication->generateKey($type, $ticket->id, $recipient->id, $deduplicationRef);

                if ($this->deduplication->isDuplicate($deduplicationKey, $deduplicationWindow)) {
                    $this->deduplication->logDelivery(null, $recipient->id, $type, 'skipped', $deduplicationKey, 'Duplicate detected');

                    continue;
                }

                if (! NotificationType::tryFrom($type)) {
                    Log::error("Invalid notification type: {$type}");

                    continue;
                }

                if (! in_array($severity, ['info', 'success', 'warning', 'critical'])) {
                    $severity = 'info';
                }

                if ($actionUrl) {
                    if (! str_starts_with($actionUrl, '/') || str_contains($actionUrl, '//')) {
                        $actionUrl = null;
                    }
                }

                // Redact metadata
                unset($metadata['password'], $metadata['token'], $metadata['secret'], $metadata['.env']);

                $payload = [
                    'type' => $type,
                    'severity' => $severity,
                    'title' => $title,
                    'message' => $message,
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'action_url' => $actionUrl,
                    'actor_id' => $actorId,
                    'metadata' => $metadata,
                ];

                DB::transaction(function () use ($recipient, $payload, $type, $deduplicationKey, &$sentCount) {
                    $notification = new TicketAlertNotification($payload);
                    $recipient->notify($notification);

                    // Retrieve the last inserted notification ID
                    // Laravel Database notification creates an ID inside the DatabaseChannel, so we might need a workaround to get the exact ID, but for now we log it without ID or get latest
                    $dbNotification = $recipient->notifications()->latest()->first();

                    $this->deduplication->logDelivery(
                        $dbNotification?->id,
                        $recipient->id,
                        $type,
                        'delivered',
                        $deduplicationKey
                    );
                    $sentCount++;
                });
            }
        } catch (\Throwable $e) {
            Log::error("Failed to dispatch notification: {$e->getMessage()}", [
                'ticket_id' => $ticket->id,
                'type' => $type,
            ]);
            // Do not throw, avoid interrupting main business logic
        }

        return $sentCount;
    }

    private function shouldSendToUser(int $userId, string $type, string $severity): bool
    {
        if ($severity === 'critical') {
            return true; // Critical cannot be disabled
        }

        $preference = NotificationPreference::where('user_id', $userId)
            ->where('notification_type', $type)
            ->first();

        if (! $preference) {
            return true; // Default enabled
        }

        if (! $preference->in_app_enabled) {
            return false;
        }

        if ($preference->muted_until && $preference->muted_until->isFuture()) {
            return false;
        }

        return true;
    }
}
