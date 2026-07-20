<?php

namespace App\Policies;

use App\Models\TicketDeployment;
use App\Models\User;

class TicketDeploymentPolicy
{
    public function view(User $user, TicketDeployment $deployment): bool
    {
        return $user->hasPermission('ticket.deployment.view');
    }

    public function manage(User $user, TicketDeployment $deployment): bool
    {
        return $user->hasRole('it_lead') || ($user->hasRole('pic') && $deployment->ticket->current_assignee_id === $user->id);
    }
}
