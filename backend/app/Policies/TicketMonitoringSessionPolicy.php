<?php

namespace App\Policies;

use App\Models\TicketMonitoringSession;
use App\Models\User;

class TicketMonitoringSessionPolicy
{
    public function view(User $user, TicketMonitoringSession $session): bool
    {
        return $user->hasPermission('ticket.monitoring.view');
    }

    public function manage(User $user, TicketMonitoringSession $session): bool
    {
        return $user->hasRole('it_lead') || ($user->hasRole('pic') && $session->ticket->current_assignee_id === $user->id);
    }
}
