<?php

namespace App\Services;

use App\Models\KnowledgeBaseArticle;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\TicketAlertNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KnowledgeBaseNotificationService
{
    public function __construct(private NotificationDeduplicationService $deduplication) {}

    public function dispatch(
        KnowledgeBaseArticle $article,
        string $type,
        string $severity,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?int $actorId = null,
        array $metadata = []
    ): int {
        $sentCount = 0;
        try {
            $recipients = $this->resolveRecipients($article, $type);

            foreach ($recipients as $recipient) {
                // Check preferences
                if (! $this->shouldSendToUser($recipient->id, $type, $severity)) {
                    continue;
                }

                $deduplicationKey = $this->deduplication->generateKey($type, $article->id, $recipient->id, 'kb_'.$article->current_version);

                if ($this->deduplication->isDuplicate($deduplicationKey, 1440)) {
                    $this->deduplication->logDelivery(null, $recipient->id, $type, 'skipped', $deduplicationKey, 'Duplicate detected');

                    continue;
                }

                if (! in_array($severity, ['info', 'success', 'warning', 'critical'], true)) {
                    $severity = 'info';
                }

                $payload = [
                    'type' => $type,
                    'severity' => $severity,
                    'title' => $title,
                    'message' => $message,
                    'action_url' => $actionUrl,
                    'actor_id' => $actorId,
                    'metadata' => array_merge($metadata, [
                        'article_id' => $article->id,
                        'article_number' => $article->article_number,
                    ]),
                ];

                DB::transaction(function () use ($recipient, $payload, $type, $deduplicationKey, &$sentCount) {
                    $notification = new TicketAlertNotification($payload);
                    $recipient->notify($notification);

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
            Log::error("Failed to dispatch KB notification: {$e->getMessage()}", [
                'article_id' => $article->id,
                'type' => $type,
            ]);
        }

        return $sentCount;
    }

    private function resolveRecipients(KnowledgeBaseArticle $article, string $type): array
    {
        $users = [];

        if ($type === 'knowledge_article_submitted') {
            // Send to all reviewers (IT Leads)
            $users = User::whereHas('role', function ($q) {
                $q->where('key', 'it_lead');
            })->where('is_active', true)->get()->all();
        } else {
            // Send to the author
            $author = User::find($article->author_id);
            if ($author && $author->is_active) {
                $users[] = $author;
            }
        }

        return $users;
    }

    private function shouldSendToUser(int $userId, string $type, string $severity): bool
    {
        if ($severity === 'critical') {
            return true;
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
