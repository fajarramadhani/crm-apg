<?php

namespace App\Services;

final class PublicIdentityNormalizer
{
    public function email(?string $email): ?string
    {
        $normalized = strtolower(trim((string) $email));

        return $normalized === '' ? null : $normalized;
    }

    public function phone(?string $phone): ?string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }
        if (! preg_match('/^\+?[0-9\s().-]+$/', $raw)) {
            return $raw;
        }

        $normalized = preg_replace('/[^0-9+]/', '', $raw) ?? '';
        if (str_starts_with($normalized, '+62')) {
            $normalized = substr($normalized, 1);
        } elseif (str_starts_with($normalized, '0')) {
            $normalized = '62'.substr($normalized, 1);
        }

        return $normalized === '' ? null : $normalized;
    }
}
