<?php

namespace App\Services;

use App\Models\PublicTicketTrackingToken;
use App\Models\Ticket;

final readonly class PublicTicketCreationResult
{
    public function __construct(
        public Ticket $ticket,
        public PublicTicketTrackingToken $trackingToken,
        public string $rawToken,
        public string $trackingUrl,
    ) {}
}
