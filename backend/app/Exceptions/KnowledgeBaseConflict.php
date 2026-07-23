<?php

namespace App\Exceptions;

use RuntimeException;

class KnowledgeBaseConflict extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $currentStatus = null)
    {
        parent::__construct($message);
    }
}
