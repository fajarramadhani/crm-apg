<?php

namespace App\Services;

use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

final class PublicTicketTrackingKeyRing
{
    public function activeVersion(): int
    {
        $value = config('public_tracking.key_version');
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1 || (int) $value > 65535) {
            throw new RuntimeException('PUBLIC_TICKET_TRACKING_KEY_VERSION must be an integer between 1 and 65535.');
        }

        $version = (int) $value;
        $this->keyFor($version);

        return $version;
    }

    public function keyFor(int $version): string
    {
        $keys = $this->keys();
        if (! array_key_exists($version, $keys)) {
            throw new RuntimeException("Public tracking key version {$version} is unavailable.");
        }

        return $keys[$version];
    }

    public function validate(): void
    {
        $this->activeVersion();
    }

    /** @return array<int, string> */
    private function keys(): array
    {
        $encodedRing = config('public_tracking.keys');
        $legacy = config('public_tracking.key');
        if (filled($encodedRing) && filled($legacy)) {
            throw new RuntimeException('Configure either PUBLIC_TICKET_TRACKING_KEYS or PUBLIC_TICKET_TRACKING_KEY, not both.');
        }

        if (filled($encodedRing)) {
            if (! is_string($encodedRing)) {
                throw new RuntimeException('PUBLIC_TICKET_TRACKING_KEYS must be a JSON object.');
            }
            try {
                $decoded = json_decode($encodedRing, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new RuntimeException('PUBLIC_TICKET_TRACKING_KEYS must be valid JSON.');
            }
            if (! is_array($decoded) || $decoded === []) {
                throw new RuntimeException('PUBLIC_TICKET_TRACKING_KEYS must be a non-empty JSON object.');
            }
            $keys = [];
            foreach ($decoded as $version => $key) {
                $version = (string) $version;
                if (! preg_match('/\A(?:[1-9][0-9]{0,4})\z/', $version) || (int) $version > 65535) {
                    throw new RuntimeException('Public tracking key versions must be canonical integers between 1 and 65535.');
                }
                $keys[(int) $version] = $this->decodeKey($key);
            }

            return $keys;
        }

        if (blank($legacy) && ! app()->environment('production')) {
            $legacy = config('app.key');
            if (blank($legacy)) {
                $legacy = 'base64:'.base64_encode(str_repeat("\0", 32));
            }
        }
        $value = config('public_tracking.key_version');
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1 || (int) $value > 65535) {
            throw new RuntimeException('PUBLIC_TICKET_TRACKING_KEY_VERSION must be an integer between 1 and 65535.');
        }

        return [(int) $value => $this->decodeKey($legacy)];
    }

    private function decodeKey(mixed $configured): string
    {
        if (! is_string($configured) || blank($configured)) {
            throw new RuntimeException('Public ticket tracking key material is required.');
        }
        $key = Str::startsWith($configured, 'base64:')
            ? base64_decode(Str::after($configured, 'base64:'), true)
            : $configured;
        if (! is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('Public ticket tracking keys must contain at least 32 bytes of key material.');
        }

        return $key;
    }
}
