<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketAlertNotification extends Notification
{
    use Queueable;

    private array $payload;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->payload['type'],
            'severity' => $this->payload['severity'] ?? 'info',
            'title' => $this->payload['title'] ?? '',
            'message' => $this->payload['message'] ?? '',
            'ticket_id' => $this->payload['ticket_id'] ?? null,
            'ticket_number' => $this->payload['ticket_number'] ?? null,
            'action_url' => $this->payload['action_url'] ?? null,
            'actor_id' => $this->payload['actor_id'] ?? null,
            'metadata' => $this->payload['metadata'] ?? [],
        ];
    }
}
