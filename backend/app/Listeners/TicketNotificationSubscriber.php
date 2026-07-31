<?php

namespace App\Listeners;

use App\Enums\RequesterCategory;
use App\Events\TicketAssigned;
use App\Events\TicketClosed;
use App\Events\TicketDeploymentFailed;
use App\Events\TicketDeploymentScheduled;
use App\Events\TicketMonitoringStarted;
use App\Events\TicketQaAssigned;
use App\Events\TicketQaFailed;
use App\Events\TicketRejected;
use App\Events\TicketReleaseApprovalRequested;
use App\Events\TicketReleaseReady;
use App\Events\TicketRequesterConfirmationRequested;
use App\Events\TicketSubmitted;
use App\Events\TicketUatApproved;
use App\Events\TicketUatAssigned;
use App\Events\TicketValidated;
use App\Services\TicketNotificationService;
use Illuminate\Events\Dispatcher;

class TicketNotificationSubscriber
{
    public function __construct(private TicketNotificationService $notificationService) {}

    public function handleTicketSubmitted(TicketSubmitted $event): void
    {
        $ticket = $event->ticket->loadMissing(['requester:id,name', 'application:id,name']);
        $category = RequesterCategory::tryFrom((string) $ticket->request_category)?->label() ?? 'Belum tersedia';
        $system = $ticket->application?->name ?? 'Belum tersedia';
        $urgency = $ticket->urgency ? strtoupper($ticket->urgency) : 'Belum tersedia';

        $this->notificationService->dispatch(
            ticket: $ticket,
            type: 'supervisor_validation_required',
            severity: 'info',
            title: 'Pengajuan Tiket Baru',
            message: "{$ticket->ticket_number} | {$category} | {$system} | {$urgency} | {$ticket->title} | Requester: {$ticket->requester?->name}",
            actionUrl: '/supervisor/validation-queue'
        );
        $this->notificationService->dispatch(
            ticket: $ticket,
            type: 'ticket_submitted',
            severity: 'success',
            title: 'Tiket Berhasil Diajukan',
            message: "Tiket {$ticket->ticket_number} berhasil diajukan.",
            actionUrl: "/user/tickets/{$ticket->id}"
        );
    }

    public function handleTicketValidated(TicketValidated $event): void
    {
        $this->notificationService->dispatch(
            ticket: $event->ticket,
            type: 'triage_required',
            severity: 'info',
            title: 'Ticket Triage Required',
            message: "Ticket {$event->ticket->ticket_number} has been validated and needs triage.",
            actionUrl: '/it-lead/triage-queue'
        );
    }

    public function handleTicketAssigned(TicketAssigned $event): void
    {
        $this->notificationService->dispatch(
            ticket: $event->ticket,
            type: 'pic_assigned',
            severity: 'info',
            title: 'New Assignment',
            message: "You have been assigned to ticket {$event->ticket->ticket_number}.",
            actionUrl: '/pic/assignments'
        );
    }

    public function handleTicketQaAssigned(TicketQaAssigned $event): void
    {
        $this->notificationService->dispatch(
            ticket: $event->ticket,
            type: 'qa_assignment_required',
            severity: 'info',
            title: 'QA Assignment',
            message: "You have been assigned as QA for ticket {$event->ticket->ticket_number}.",
            actionUrl: '/qa/assignments'
        );
    }

    public function handleTicketQaFailed(TicketQaFailed $event): void
    {
        $this->notificationService->dispatch(
            ticket: $event->ticket,
            type: 'qa_failed',
            severity: 'warning',
            title: 'QA Failed',
            message: "QA testing failed for ticket {$event->ticket->ticket_number}.",
            actionUrl: "/tickets/{$event->ticket->id}"
        );
    }

    public function handleTicketUatAssigned(TicketUatAssigned $event): void
    {
        $this->notificationService->dispatch(
            ticket: $event->ticket,
            type: 'uat_assignment_required',
            severity: 'info',
            title: 'UAT Required',
            message: "Ticket {$event->ticket->ticket_number} is ready for UAT.",
            actionUrl: '/requester/uat-assignments'
        );
    }

    public function handleTicketUatApproved(TicketUatApproved $event): void
    {
        $this->notificationService->dispatch(
            ticket: $event->ticket,
            type: 'approval_required',
            severity: 'success',
            title: 'UAT Approved',
            message: "UAT approved for ticket {$event->ticket->ticket_number}. Release preparation can begin.",
            actionUrl: "/tickets/{$event->ticket->id}"
        );
    }

    public function handleTicketReleaseApprovalRequested(TicketReleaseApprovalRequested $event): void
    {
        $this->notificationService->dispatch(
            ticket: $event->ticket,
            type: 'business_approval_required',
            severity: 'info',
            title: 'Business Approval Required',
            message: "Release approval requested for ticket {$event->ticket->ticket_number}.",
            actionUrl: '/manager/business-approval-queue'
        );
        $this->notificationService->dispatch(
            ticket: $event->ticket,
            type: 'technical_approval_required',
            severity: 'info',
            title: 'Technical Approval Required',
            message: "Technical approval requested for ticket {$event->ticket->ticket_number}.",
            actionUrl: '/it-lead/technical-approval-queue'
        );
    }

    public function handleTicketReleaseReady(TicketReleaseReady $event): void
    {
        $this->notificationService->dispatch(
            ticket: $event->ticket,
            type: 'release_ready',
            severity: 'success',
            title: 'Release Ready',
            message: "Ticket {$event->ticket->ticket_number} is ready for release.",
            actionUrl: "/tickets/{$event->ticket->id}"
        );
    }

    public function handleTicketDeploymentScheduled(TicketDeploymentScheduled $event): void
    {
        $this->notificationService->dispatch(
            ticket: $event->ticket,
            type: 'deployment_scheduled',
            severity: 'info',
            title: 'Deployment Scheduled',
            message: "Deployment scheduled for ticket {$event->ticket->ticket_number}.",
            actionUrl: "/tickets/{$event->ticket->id}"
        );
    }

    public function handleTicketDeploymentFailed(TicketDeploymentFailed $event): void
    {
        $this->notificationService->dispatch(
            ticket: $event->ticket,
            type: 'deployment_failed',
            severity: 'critical',
            title: 'Deployment Failed',
            message: "Deployment failed for ticket {$event->ticket->ticket_number}.",
            actionUrl: "/tickets/{$event->ticket->id}"
        );
    }

    public function handleTicketMonitoringStarted(TicketMonitoringStarted $event): void
    {
        // This simulates post-release issue detected if it was an incident.
        // Actually, the prompt says TicketPostReleaseIssueDetected, but there is no such event in the existing list.
        // Monitoring starts when deployment completes. Let's just use what we have or map if there's an incident event.
    }

    public function handleTicketRequesterConfirmationRequested(TicketRequesterConfirmationRequested $event): void
    {
        $this->notificationService->dispatch(
            ticket: $event->ticket,
            type: 'requester_confirmation_required',
            severity: 'info',
            title: 'Confirmation Required',
            message: "Ticket {$event->ticket->ticket_number} is completed. Please confirm.",
            actionUrl: "/tickets/{$event->ticket->id}"
        );
    }

    public function handleTicketRejected(TicketRejected $event): void
    {
        $this->notificationService->dispatch(
            ticket: $event->ticket,
            type: 'requester_rejected',
            severity: 'warning',
            title: 'Ticket Rejected',
            message: "Ticket {$event->ticket->ticket_number} has been rejected.",
            actionUrl: "/tickets/{$event->ticket->id}"
        );
    }

    public function handleTicketClosed(TicketClosed $event): void
    {
        $this->notificationService->dispatch(
            ticket: $event->ticket,
            type: 'ticket_closed',
            severity: 'success',
            title: 'Ticket Closed',
            message: "Ticket {$event->ticket->ticket_number} is closed.",
            actionUrl: "/tickets/{$event->ticket->id}"
        );
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            TicketSubmitted::class => 'handleTicketSubmitted',
            TicketValidated::class => 'handleTicketValidated',
            TicketAssigned::class => 'handleTicketAssigned',
            TicketQaAssigned::class => 'handleTicketQaAssigned',
            TicketQaFailed::class => 'handleTicketQaFailed',
            TicketUatAssigned::class => 'handleTicketUatAssigned',
            TicketUatApproved::class => 'handleTicketUatApproved',
            TicketReleaseApprovalRequested::class => 'handleTicketReleaseApprovalRequested',
            TicketReleaseReady::class => 'handleTicketReleaseReady',
            TicketDeploymentScheduled::class => 'handleTicketDeploymentScheduled',
            TicketDeploymentFailed::class => 'handleTicketDeploymentFailed',
            TicketRequesterConfirmationRequested::class => 'handleTicketRequesterConfirmationRequested',
            TicketRejected::class => 'handleTicketRejected',
            TicketClosed::class => 'handleTicketClosed',
            TicketMonitoringStarted::class => 'handleTicketMonitoringStarted', // Placeholder for incident logic if any
        ];
    }
}
