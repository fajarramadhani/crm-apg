<?php

namespace App\Policies;

use App\Models\TicketPostReleaseIncident;
use App\Models\User;

class TicketPostReleaseIncidentPolicy
{
    public function view(User $user, TicketPostReleaseIncident $incident): bool
    {
        return $user->hasPermission('ticket.monitoring.view');
    }

    public function manage(User $user, TicketPostReleaseIncident $incident): bool
    {
        return $user->hasPermission('ticket.monitoring.incident.manage') &&
               ($user->hasRole('it_lead') || ($user->hasRole('pic') && $incident->ticket->current_assignee_id === $user->id));
    }
}
