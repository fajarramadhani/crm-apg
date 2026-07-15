<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;

class TicketTransferred
{
    use Dispatchable;

    public function __construct(public Ticket $ticket) {}
}
