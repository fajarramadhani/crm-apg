<?php

namespace App\Listeners;

use App\Events\PublicRequesterActionCompleted;
use App\Events\TicketAnalysisStarted;
use App\Events\TicketAssigned;
use App\Events\TicketClosed;
use App\Events\TicketRejected;
use App\Events\TicketRevisionRequested;
use App\Events\TicketSubmitted;
use App\Events\TicketUatApproved;
use App\Events\TicketUatAssigned;
use App\Models\Ticket;
use App\Services\PublicTicketTrackingService;
use App\Services\WhatsAppNotificationService;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

final class WhatsAppNotificationSubscriber
{
    public function __construct(
        private readonly WhatsAppNotificationService $notifications,
        private readonly PublicTicketTrackingService $tracking,
    ) {}

    public function handleTicketSubmitted(TicketSubmitted $event): void
    {
        $ticket = $event->ticket->loadMissing(['requester:id,name,phone', 'branch:id,name', 'category:id,name']);
        $common = [
            'requester_name' => $ticket->requester_name ?: $ticket->requester?->name ?: 'Pemohon',
            'ticket_number' => $ticket->ticket_number,
            'title' => $ticket->title,
            'category' => $ticket->category?->name ?: 'Umum',
            'status' => 'Menunggu validasi',
        ];
        $requesterNumber = $ticket->requester_phone ?: $ticket->requester?->phone;
        $trackingUrl = $this->requesterUrl($ticket);
        if ($trackingUrl !== null) {
            $this->notifications->queueSafely('ticket_created', $ticket, $requesterNumber, 'requester',
                $ticket->requester_id ? 'user' : 'public_requester', 'ticket_created_requester',
                [...$common, 'tracking_url' => $trackingUrl], "ticket-created:{$ticket->id}:requester");
        }

        if (config('whatsapp.recipients.ticket_created_it_support')) {
            $this->notifications->notifyItSupport('ticket_created', $ticket, [
                ...$common,
                'branch' => $ticket->branch?->name ?: 'Kantor Pusat',
                'internal_url' => $this->internalUrl("supervisor-it/tickets/{$ticket->id}"),
            ], "ticket-created:{$ticket->id}:it-support");
        }
    }

    public function handleTicketAssigned(TicketAssigned $event): void
    {
        $assignee = $event->assignee->fresh(['role:id,key']) ?? $event->assignee;
        $ticket = $event->ticket->loadMissing(['requester:id,name,phone', 'branch:id,name', 'finalPriority:id,name', 'requestedPriority:id,name']);
        $this->notifications->queueSafely('ticket_assigned', $ticket, $assignee->phone, 'pic', 'user',
            'ticket_assigned_pic', [
                'pic_name' => $assignee->name,
                'ticket_number' => $ticket->ticket_number,
                'title' => $ticket->title,
                'priority' => $ticket->finalPriority?->name ?: $ticket->requestedPriority?->name ?: 'Belum ditentukan',
                'requester_name' => $ticket->requester_name ?: $ticket->requester?->name ?: 'Pemohon',
                'branch' => $ticket->branch?->name ?: 'Kantor Pusat',
                'internal_url' => $this->internalUrl("pic/tickets/{$ticket->id}"),
            ], "ticket-assigned:{$ticket->id}:pic-{$assignee->id}");
    }

    public function handlePublicRequesterActionCompleted(PublicRequesterActionCompleted $event): void
    {
        if ($event->outcome === 'rejected') {
            $this->notifyRequesterStatus($event->ticket, $event->action.'-rejected', 'Perlu perbaikan');
        }
    }

    public function handleTicketUatAssigned(TicketUatAssigned $event): void
    {
        $this->notifyRequesterStatus($event->ticket, 'waiting-uat', 'Menunggu UAT');
    }

    public function handleTicketAnalysisStarted(TicketAnalysisStarted $event): void
    {
        $this->notifyRequesterStatus($event->ticket, 'analysis-started', 'Sedang diproses');
    }

    public function handleTicketRevisionRequested(TicketRevisionRequested $event): void
    {
        $this->notifyRequesterStatus($event->ticket, 'revision-requested', 'Menunggu informasi atau revisi dari pemohon');
    }

    public function handleTicketUatApproved(TicketUatApproved $event): void
    {
        $this->notifyRequesterStatus($event->ticket, 'uat-approved', 'UAT disetujui');
    }

    public function handleTicketRejected(TicketRejected $event): void
    {
        $this->notifyRequesterStatus($event->ticket, 'rejected', 'Ditolak');
    }

    public function handleTicketClosed(TicketClosed $event): void
    {
        $ticket = $event->ticket->loadMissing('requester:id,name,phone');
        $url = $this->requesterUrl($ticket);
        if ($url === null) {
            return;
        }
        $this->notifications->queueSafely('ticket_completed', $ticket,
            $ticket->requester_phone ?: $ticket->requester?->phone, 'requester', $ticket->requester_id ? 'user' : 'public_requester',
            'ticket_completed_requester', [
                'requester_name' => $ticket->requester_name ?: $ticket->requester?->name ?: 'Pemohon',
                'ticket_number' => $ticket->ticket_number,
                'resolution_summary' => $ticket->closures()->latest()->value('resolution_summary') ?: 'Permintaan telah diselesaikan.',
                'tracking_url' => $url,
            ],
            "ticket-completed:{$ticket->id}:requester");
    }

    private function notifyRequesterStatus(Ticket $ticket, string $statusKey, string $statusLabel): void
    {
        $ticket->loadMissing('requester:id,name,phone');
        $url = $this->requesterUrl($ticket);
        if ($url === null) {
            return;
        }
        $this->notifications->queueSafely('important_status_changed', $ticket,
            $ticket->requester_phone ?: $ticket->requester?->phone, 'requester', $ticket->requester_id ? 'user' : 'public_requester',
            'important_status_requester', [
                'requester_name' => $ticket->requester_name ?: $ticket->requester?->name ?: 'Pemohon',
                'ticket_number' => $ticket->ticket_number,
                'status' => $statusLabel,
                'tracking_url' => $url,
            ],
            "ticket-status:{$ticket->id}:{$statusKey}:requester");
    }

    private function requesterUrl(Ticket $ticket): ?string
    {
        if ($ticket->requester_id) {
            return $this->internalUrl("user/tickets/{$ticket->id}");
        }
        try {
            $status = $this->tracking->status($ticket);

            return $status['state'] === 'active' ? $status['tracking_url'] : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function internalUrl(string $path): string
    {
        $base = rtrim(trim(explode(',', (string) config('public_tracking.frontend_url', config('app.url')))[0]), '/');

        return $base.'/'.ltrim($path, '/');
    }

    public function subscribe(Dispatcher $events): void
    {
        $listeners = [
            TicketSubmitted::class => 'handleTicketSubmitted',
            TicketAssigned::class => 'handleTicketAssigned',
            TicketAnalysisStarted::class => 'handleTicketAnalysisStarted',
            TicketRevisionRequested::class => 'handleTicketRevisionRequested',
            PublicRequesterActionCompleted::class => 'handlePublicRequesterActionCompleted',
            TicketUatAssigned::class => 'handleTicketUatAssigned',
            TicketUatApproved::class => 'handleTicketUatApproved',
            TicketClosed::class => 'handleTicketClosed',
            TicketRejected::class => 'handleTicketRejected',
        ];

        foreach ($listeners as $event => $method) {
            $events->listen($event, function (object $domainEvent) use ($method): void {
                try {
                    $this->{$method}($domainEvent);
                } catch (Throwable $exception) {
                    Log::error('WhatsApp notification listener failed without affecting ticket processing.', [
                        'event' => $domainEvent::class,
                        'exception_class' => $exception::class,
                    ]);
                }
            });
        }
    }
}
