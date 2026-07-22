<?php

namespace App\Services;

use App\Models\NotificationDeliveryLog;
use Carbon\Carbon;

class NotificationDeduplicationService
{
    public function isDuplicate(string $deduplicationKey, ?int $windowMinutes = null): bool
    {
        $query = NotificationDeliveryLog::where('deduplication_key', $deduplicationKey)
            ->whereIn('status', ['pending', 'delivered']);

        if ($windowMinutes !== null) {
            $query->where('created_at', '>=', Carbon::now()->subMinutes($windowMinutes));
        }

        return $query->exists();
    }

    public function generateKey(string $type, int $ticketId, int $recipientId, ?string $referenceId = null): string
    {
        $key = "{$type}:ticket-{$ticketId}:user-{$recipientId}";
        if ($referenceId) {
            $key .= ":{$referenceId}";
        }

        return $key;
    }

    public function logDelivery(
        ?string $notificationId,
        int $userId,
        string $type,
        string $status,
        string $deduplicationKey,
        ?string $failureReason = null
    ): NotificationDeliveryLog {
        $finalKey = $deduplicationKey;
        if ($status !== 'delivered' && $status !== 'pending') {
            $finalKey .= ':'.$status.':'.uniqid();
        }

        return NotificationDeliveryLog::create([
            'notification_id' => $notificationId,
            'user_id' => $userId,
            'notification_type' => $type,
            'status' => $status,
            'deduplication_key' => $finalKey,
            'delivered_at' => $status === 'delivered' ? now() : null,
            'failure_reason' => $failureReason,
        ]);
    }
}
