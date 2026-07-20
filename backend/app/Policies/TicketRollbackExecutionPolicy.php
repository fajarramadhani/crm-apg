<?php

namespace App\Policies;

use App\Models\TicketRollbackExecution;
use App\Models\User;

class TicketRollbackExecutionPolicy
{
    public function view(User $user, TicketRollbackExecution $rollback): bool
    {
        return $user->hasPermission('ticket.rollback.view');
    }

    public function manage(User $user, TicketRollbackExecution $rollback): bool
    {
        return $user->hasRole('it_lead') || ($user->hasRole('pic') && $rollback->ticket->current_assignee_id === $user->id);
    }
}
