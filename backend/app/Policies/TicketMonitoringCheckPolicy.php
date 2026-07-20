<?php

namespace App\Policies;

use App\Models\TicketMonitoringCheck;
use App\Models\User;

class TicketMonitoringCheckPolicy
{
    public function view(User $user, TicketMonitoringCheck $check): bool
    {
        return $user->hasPermission('ticket.monitoring.view');
    }

    public function manage(User $user, TicketMonitoringCheck $check): bool
    {
        return $user->hasPermission('ticket.monitoring.check.manage') &&
               ($user->hasRole('it_lead') || ($user->hasRole('pic') && $check->monitoringSession->ticket->current_assignee_id === $user->id));
    }
}
