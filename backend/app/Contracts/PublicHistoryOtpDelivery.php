<?php

namespace App\Contracts;

interface PublicHistoryOtpDelivery
{
    public function deliver(string $channel, string $destination, string $code, int $expiresInMinutes): void;
}
