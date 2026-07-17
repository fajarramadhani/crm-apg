<?php

namespace App\Events;

use App\Models\TicketUatFinding;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketUatFindingCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public TicketUatFinding $finding, public User $actor) {}
}
