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
}
