<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;

class TicketSubmitted
{
    use Dispatchable;

    public function __construct(public Ticket $ticket) {}
}
