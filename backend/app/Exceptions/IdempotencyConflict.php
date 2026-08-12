<?php

namespace App\Exceptions;

use RuntimeException;

class IdempotencyConflict extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            'payload_mismatch' => 'The Idempotency-Key has already been used with a different request payload.',
            default => 'A request with this Idempotency-Key is still in progress.',
        });
    }
}
