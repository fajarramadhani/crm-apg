<?php

namespace Tests\Feature;

use App\Services\PublicTicketTrackingKeyRing;
use App\Services\PublicTicketTrackingService;
use RuntimeException;
use Tests\TestCase;

class PublicTicketTrackingKeyRingTest extends TestCase
{
    public function test_legacy_mode_preserves_the_existing_derivation_formula(): void
    {
        config()->set('public_tracking.key', str_repeat('k', 32));
        config()->set('public_tracking.keys', null);
        config()->set('public_tracking.key_version', 1);
        $nonce = str_repeat('ab', 32);
        $domain = 'apg-crm:public-ticket-tracking:v1';
        $payload = $domain."\0".hex2bin($nonce)."\0".'12'."\0".'3'."\0".'1';
        $derivedSecret = hash_hmac('sha256', $domain, str_repeat('k', 32), true);
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $derivedSecret, true)), '+/', '-_'), '=');

        $this->assertSame($expected, app(PublicTicketTrackingService::class)->deriveRawToken($nonce, 12, 3, 1));
    }

    public function test_ring_requires_the_active_version(): void
    {
        config()->set('public_tracking.key', null);
        config()->set('public_tracking.keys', json_encode(['1' => str_repeat('a', 32)], JSON_THROW_ON_ERROR));
        config()->set('public_tracking.key_version', 2);

        $this->expectException(RuntimeException::class);
        app(PublicTicketTrackingKeyRing::class)->validate();
    }

    public function test_ring_rejects_ambiguous_legacy_configuration(): void
    {
        config()->set('public_tracking.key', str_repeat('a', 32));
        config()->set('public_tracking.keys', json_encode(['1' => str_repeat('b', 32)], JSON_THROW_ON_ERROR));

        $this->expectException(RuntimeException::class);
        app(PublicTicketTrackingKeyRing::class)->validate();
    }
}
