<?php

namespace App\Policies;

use App\Models\TicketClosure;
use App\Models\User;

class TicketClosurePolicy
{
    public function view(User $user, TicketClosure $closure): bool
    {
        return $user->hasPermission('ticket.closure.view');
    }
}
