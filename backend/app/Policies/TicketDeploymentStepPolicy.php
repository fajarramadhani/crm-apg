<?php

namespace App\Policies;

use App\Models\TicketDeploymentStep;
use App\Models\User;

class TicketDeploymentStepPolicy
{
    public function view(User $user, TicketDeploymentStep $step): bool
    {
        return $user->hasPermission('ticket.deployment.view');
    }

    public function manage(User $user, TicketDeploymentStep $step): bool
    {
        return $user->hasPermission('ticket.deployment.step.manage') &&
               ($user->hasRole('it_lead') || ($user->hasRole('pic') && $step->deployment->ticket->current_assignee_id === $user->id));
    }
}
