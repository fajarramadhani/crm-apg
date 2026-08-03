<?php

namespace App\Services;

use App\Contracts\PublicHistoryOtpDelivery;
use App\Mail\PublicHistoryOtpMail;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

final class MailPublicHistoryOtpDelivery implements PublicHistoryOtpDelivery
{
    public function deliver(string $channel, string $destination, string $code, int $expiresInMinutes): void
    {
        if ($channel !== 'email') {
            throw new InvalidArgumentException('Unsupported OTP delivery channel.');
        }

        Mail::to($destination)->send(new PublicHistoryOtpMail($code, $expiresInMinutes));
    }
}
