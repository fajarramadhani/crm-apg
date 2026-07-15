<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;

class TicketResubmitted
{
    use Dispatchable;

    public function __construct(public Ticket $ticket) {}
}
