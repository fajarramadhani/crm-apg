<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;

class TicketValidated
{
    use Dispatchable;

    public function __construct(public Ticket $ticket) {}
}
