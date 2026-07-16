<?php

namespace App\Events;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

final class TicketDevelopmentStarted
{
    use Dispatchable;

    public function __construct(public Ticket $ticket, public User $actor) {}
}
