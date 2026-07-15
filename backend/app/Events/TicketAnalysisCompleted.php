<?php

namespace App\Events;

use App\Models\Ticket;
use App\Models\TicketAnalysis;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

final class TicketAnalysisCompleted
{
    use Dispatchable;

    public function __construct(public Ticket $ticket, public User $actor, public TicketAnalysis $analysis) {}
}
