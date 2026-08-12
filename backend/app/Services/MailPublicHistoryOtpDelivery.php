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
        if ($this->containsUnsafeTransport((string) config('mail.default'))) {
            throw new InvalidArgumentException('The configured mailer cannot deliver verification codes safely.');
        }

        Mail::to($destination)->send(new PublicHistoryOtpMail($code, $expiresInMinutes));
    }

    private function containsUnsafeTransport(string $mailer, array $visited = []): bool
    {
        if (in_array($mailer, $visited, true)) {
            return true;
        }
        $configuration = config("mail.mailers.{$mailer}");
        if (! is_array($configuration)) {
            return true;
        }
        $transport = $configuration['transport'] ?? null;
        if (in_array($transport, ['log', 'array'], true)) {
            return true;
        }
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            return collect($configuration['mailers'] ?? [])->contains(
                fn ($nested) => ! is_string($nested) || $this->containsUnsafeTransport($nested, [...$visited, $mailer])
            );
        }

        return false;
    }
}
