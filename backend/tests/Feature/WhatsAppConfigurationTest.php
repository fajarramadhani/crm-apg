<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppGateway;
use App\Services\WhatsApp\FonnteWhatsAppGateway;
use App\Services\WhatsAppNotificationService;
use Tests\TestCase;

class WhatsAppConfigurationTest extends TestCase
{
    public function test_indonesian_numbers_are_normalized_and_invalid_numbers_rejected(): void
    {
        $this->assertSame('6281234567890', WhatsAppNotificationService::normalizePhoneNumber('0812-3456-7890'));
        $this->assertSame('6281234567890', WhatsAppNotificationService::normalizePhoneNumber('+62 812 3456 7890'));
        $this->assertSame('6281234567890', WhatsAppNotificationService::normalizePhoneNumber('006281234567890'));
        $this->assertNull(WhatsAppNotificationService::normalizePhoneNumber('123'));
        $this->assertNull(WhatsAppNotificationService::normalizePhoneNumber('abcdef'));
        $this->assertSame('6281******7890', WhatsAppNotificationService::maskPhoneNumber('081234567890'));
    }

    public function test_fonnte_provider_is_bound(): void
    {
        config(['whatsapp.provider' => 'fonnte']);
        $this->assertInstanceOf(FonnteWhatsAppGateway::class, app(WhatsAppGateway::class));
    }

    public function test_fonnte_credentials_are_validated_when_gateway_is_used(): void
    {
        config([
            'whatsapp.provider' => 'fonnte',
            'whatsapp.fonnte.token' => '',
        ]);

        $result = app(WhatsAppGateway::class)->sendMessage('6281234567890', 'Test');

        $this->assertFalse($result['success']);
        $this->assertSame('INVALID_TOKEN', $result['error_code']);
        $this->assertFalse($result['retryable']);
    }

    public function test_unsupported_provider_is_rejected_when_gateway_is_used(): void
    {
        config(['whatsapp.provider' => 'unsupported']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported WhatsApp provider 'unsupported'.");

        app(WhatsAppGateway::class);
    }

    public function test_enabled_production_gateway_rejects_incomplete_fonnte_configuration_when_used(): void
    {
        app()->detectEnvironment(fn (): string => 'production');
        config([
            'whatsapp.enabled' => true,
            'whatsapp.provider' => 'fonnte',
            'whatsapp.fonnte.token' => 'configured-token',
            'whatsapp.fonnte.webhook_secret' => '',
            'whatsapp.fonnte.it_support_number' => '6281234567890',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WhatsApp enabled in production but mandatory Fonnte configuration is missing.');

        app(WhatsAppGateway::class)->sendMessage('6281234567890', 'Test');
    }
}
