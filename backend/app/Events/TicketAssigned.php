<?php

namespace App\Events;

use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

final class TicketAssigned
{
    use Dispatchable;

    public readonly CarbonImmutable $occurredAt;

    public function __construct(public Ticket $ticket, public User $actor, public User $assignee)
    {
        $this->occurredAt = CarbonImmutable::now();
    }
}
