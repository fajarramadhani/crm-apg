<?php

namespace App\Services;

use App\Contracts\PublicHistoryOtpDelivery;

final class InMemoryPublicHistoryOtpDelivery implements PublicHistoryOtpDelivery
{
    /** @var array<int, array{channel: string, destination: string, code: string, expires_in_minutes: int}> */
    private array $deliveries = [];

    public function deliver(string $channel, string $destination, string $code, int $expiresInMinutes): void
    {
        $this->deliveries[] = compact('channel', 'destination', 'code', 'expiresInMinutes');
    }

    /** @return array<int, array{channel: string, destination: string, code: string, expires_in_minutes: int}> */
    public function deliveries(): array
    {
        return array_map(fn (array $delivery): array => [
            'channel' => $delivery['channel'],
            'destination' => $delivery['destination'],
            'code' => $delivery['code'],
            'expires_in_minutes' => $delivery['expiresInMinutes'],
        ], $this->deliveries);
    }

    public function clear(): void
    {
        $this->deliveries = [];
    }
}
