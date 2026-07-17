<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return ($user->hasPermission('ticket.own.view') && $ticket->requester_id === $user->id)
            || ($user->hasPermission('ticket.division.view') && $ticket->current_division_id === $user->division_id)
            || ($user->hasPermission('ticket.assigned.view') && $ticket->current_assignee_id === $user->id)
            || ($user->hasPermission('ticket.assigned.view') && $ticket->qa_assignee_id === $user->id);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $ticket->requester_id === $user->id && $user->hasPermission('ticket.own.update') && in_array($ticket->status, [TicketStatus::Draft, TicketStatus::NeedRevision], true);
    }

    public function cancel(User $user, Ticket $ticket): bool
    {
        return $ticket->requester_id === $user->id && $user->hasPermission('ticket.own.cancel') && in_array($ticket->status, [TicketStatus::Draft, TicketStatus::PendingValidation, TicketStatus::NeedRevision], true);
    }

    public function manageAttachment(User $user, Ticket $ticket): bool
    {
        return $ticket->requester_id === $user->id && $user->hasPermission('ticket.own.attachment.manage') && in_array($ticket->status, [TicketStatus::Draft, TicketStatus::PendingValidation, TicketStatus::NeedRevision], true);
    }

    public function supervise(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('ticket.validation_queue.view') && $user->division_id !== null && $ticket->current_division_id === $user->division_id;
    }

    public function triage(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('ticket.triage_queue.view');
    }

    public function assignQa(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('ticket.qa.assign');
    }

    public function executeQa(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('ticket.assigned.view') && $ticket->qa_assignee_id === $user->id;
    }

    public function analyze(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('ticket.analysis.manage') && $ticket->current_assignee_id === $user->id
            && $ticket->assignments()->where('assigned_to', $user->id)->where('is_current', true)->exists();
    }

    public function reviewPlan(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('ticket.solution_plan.approve') || $user->hasPermission('ticket.solution_plan.request_revision');
    }

    public function develop(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('ticket.development.update') && $ticket->current_assignee_id === $user->id
            && $ticket->assignments()->where('assigned_to', $user->id)->where('is_current', true)->exists();
    }
}
