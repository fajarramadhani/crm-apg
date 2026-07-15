<?php

namespace App\Events;

use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

final class TicketTriageStarted
{
    use Dispatchable;

    public readonly CarbonImmutable $occurredAt;

    public function __construct(public Ticket $ticket, public User $actor)
    {
        $this->occurredAt = CarbonImmutable::now();
    }
}
