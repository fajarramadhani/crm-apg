<?php

namespace App\Events;

use App\Models\TicketQaDefect;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

final class TicketQaDefectCreated
{
    use Dispatchable;

    public function __construct(public TicketQaDefect $defect, public User $actor) {}
}
