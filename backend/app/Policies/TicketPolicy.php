<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('ticket.all.view')
            || $user->hasPermission('ticket.own.view')
            || $user->hasPermission('ticket.division.view')
            || $user->hasPermission('ticket.assigned.view');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('ticket.all.view')
            || ($user->hasPermission('ticket.own.view') && $ticket->requester_id === $user->id)
            || ($user->hasPermission('ticket.division.view') && $ticket->current_division_id === $user->division_id)
            || ($user->hasPermission('ticket.assigned.view') && ($ticket->current_assignee_id === $user->id || $ticket->assignments()->where('assigned_to', $user->id)->where('is_current', true)->exists()))
            || ($user->hasPermission('ticket.assigned.view') && $ticket->qa_assignee_id === $user->id)
            || ($user->hasPermission('ticket.uat_assignment.view') && $ticket->uat_assignee_id === $user->id);
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

    public function managePublicTracking(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('ticket.public_tracking.manage')
            && $ticket->submission_source === 'public_form';
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

    public function assignUat(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('ticket.uat.assign');
    }

    public function executeUat(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('ticket.uat_assignment.view') && $ticket->uat_assignee_id === $user->id;
    }

    public function requestReleaseApproval(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('ticket.approval_request.create');
    }

    public function businessApprove(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('ticket.business_approval.approve') && $ticket->division_id === $user->division_id;
    }

    public function technicalApprove(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('ticket.technical_approval.approve');
    }

    public function viewReleasePreparation(User $user, Ticket $ticket): bool
    {
        if (! $user->hasPermission('ticket.release_plan.view')) {
            return false;
        }

        return $user->hasRole('it_lead') || $ticket->current_assignee_id === $user->id || $ticket->release_owner_id === $user->id;
    }

    public function manageReleasePreparation(User $user, Ticket $ticket): bool
    {
        if (! $user->hasPermission('ticket.release_plan.manage')) {
            return false;
        }

        return $user->hasRole('it_lead') || ($user->hasRole('pic') && $ticket->current_assignee_id === $user->id && $ticket->assignments()->where('assigned_to', $user->id)->where('is_current', true)->exists());
    }

    public function reviewReleasePlan(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('ticket.release_plan.review') && $user->hasRole('it_lead');
    }

    public function confirmReleaseReady(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('ticket.release_readiness.confirm') && $user->hasRole('it_lead');
    }
}
