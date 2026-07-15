<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidTicketTransition extends RuntimeException
{
    public function __construct(public readonly string $currentStatus, string $message = 'Ticket transition is not allowed.')
    {
        parent::__construct($message);
    }
}
