<?php

namespace App\Contracts;

interface WhatsAppGateway
{
    /** @return array<string, mixed> */
    public function sendMessage(string $recipient, string $message): array;

    /** @return array<string, mixed> */
    public function validateNumber(string $recipient): array;

    /** @return array<string, mixed> */
    public function getDeviceProfile(): array;
}
