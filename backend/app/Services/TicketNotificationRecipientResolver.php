<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;

class TicketNotificationRecipientResolver
{
    public function resolve(Ticket $ticket, string $notificationType): Collection
    {
        $recipients = collect();

        switch ($notificationType) {
            case 'ticket_submitted':
            case 'ticket_rejected':
            case 'uat_assignment_required':
            case 'requester_confirmation_required':
            case 'ticket_closed':
                if ($ticket->requester_id) {
                    $recipients->push(User::find($ticket->requester_id));
                }
                break;

            case 'supervisor_validation_required':
                // Find supervisors in the same division
                $supervisors = User::whereHas('role', function ($q) {
                    $q->where('name', 'Supervisor');
                })->where('division_id', $ticket->requester?->division_id)
                    ->where('is_active', true)
                    ->get();
                $recipients = $recipients->merge($supervisors);
                break;

            case 'ticket_validated':
            case 'triage_required':
            case 'qa_assignment_required':
            case 'approval_rejected':
            case 'release_ready':
            case 'deployment_scheduled':
            case 'deployment_failed':
            case 'rollback_required':
            case 'monitoring_issue_detected':
            case 'requester_rejected':
            case 'sla_approaching':
            case 'sla_breached':
            case 'ticket_escalated':
                // IT Leads
                $itLeads = User::whereHas('role', function ($q) {
                    $q->where('name', 'IT Lead');
                })->where('is_active', true)->get();
                $recipients = $recipients->merge($itLeads);
                break;

            case 'business_approval_required':
                // Managers scoped to division
                $managers = User::whereHas('role', function ($q) {
                    $q->where('name', 'Manager');
                })->where('division_id', $ticket->requester?->division_id)
                    ->where('is_active', true)->get();
                $recipients = $recipients->merge($managers);
                break;

            case 'technical_approval_required':
                // IT Managers / Approvers
                $approvers = User::whereHas('role', function ($q) {
                    $q->whereIn('name', ['Manager', 'IT Lead']);
                })->where('is_active', true)->get();
                $recipients = $recipients->merge($approvers);
                break;

            case 'pic_assigned':
            case 'analysis_required':
            case 'development_started':
            case 'internal_testing_required':
            case 'release_preparation_required':
                if ($ticket->current_assignee_id) {
                    $recipients->push(User::find($ticket->current_assignee_id));
                }
                break;

            case 'ticket_inactive':
                $awaitingRequester = in_array($ticket->status->value, ['need_revision', 'awaiting_requester_confirmation', 'waiting_user']);
                if ($awaitingRequester) {
                    if ($ticket->requester_id) {
                        $recipients->push(User::find($ticket->requester_id));
                    }
                } else {
                    if ($ticket->current_assignee_id) {
                        $recipients->push(User::find($ticket->current_assignee_id));
                    }
                }
                break;

            case 'qa_failed':
            case 'qa_passed':
                if ($ticket->qa_assignee_id) {
                    $recipients->push(User::find($ticket->qa_assignee_id));
                }
                if ($ticket->current_assignee_id) {
                    $recipients->push(User::find($ticket->current_assignee_id));
                }
                break;

            case 'uat_rejected':
            case 'uat_approved':
                if ($ticket->current_assignee_id) {
                    $recipients->push(User::find($ticket->current_assignee_id));
                }
                $itLeads = User::whereHas('role', function ($q) {
                    $q->where('name', 'IT Lead');
                })->where('is_active', true)->get();
                $recipients = $recipients->merge($itLeads);
                break;
        }

        // Rule: Remove executives from ticket-level detail notifications unless it's a summary or aggregate alert, which are not handled per ticket here.
        // Executives don't get these direct ticket notifications.

        // Remove duplicates and inactive users
        return $recipients->filter(fn ($user) => $user && $user->is_active && $user->role?->name !== 'Executive')->unique('id')->values();
    }
}
