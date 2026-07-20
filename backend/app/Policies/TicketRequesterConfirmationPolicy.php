<?php

namespace App\Policies;

use App\Models\TicketRequesterConfirmation;
use App\Models\User;

class TicketRequesterConfirmationPolicy
{
    public function view(User $user, TicketRequesterConfirmation $confirmation): bool
    {
        return $user->hasPermission('ticket.requester_confirmation.view');
    }

    public function respond(User $user, TicketRequesterConfirmation $confirmation): bool
    {
        return $user->hasPermission('ticket.requester_confirmation.respond') && $confirmation->requester_id === $user->id;
    }
}
