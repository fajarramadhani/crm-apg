<?php

namespace App\Console\Commands;

use App\Services\WhatsAppNotificationService;
use App\Services\WhatsAppNotificationSettings;
use Illuminate\Console\Command;

class TestWhatsAppNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:whatsapp-test {--note= : Catatan pesan pengujian} {--force : Jalankan meskipun di luar environment local/testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim pesan pengujian WhatsApp ke nomor IT Support dari konfigurasi';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppNotificationService $service, WhatsAppNotificationSettings $settings): int
    {
        if (app()->environment('production')) {
            $this->error('Perintah pengujian WhatsApp tidak boleh dijalankan di production.');

            return static::FAILURE;
        }

        if (! app()->environment(['local', 'testing']) && ! $this->option('force')) {
            $this->error('Perintah ini hanya boleh dijalankan di environment local atau testing. Gunakan --force jika bermaksud menjalankan pada staging.');

            return static::FAILURE;
        }

        if (! $settings->enabled()) {
            $this->warn('Fitur notifikasi WhatsApp saat ini NONAKTIF berdasarkan pengaturan efektif.');

            return static::FAILURE;
        }

        $rawNumber = (string) config('whatsapp.fonnte.it_support_number');
        $maskedNumber = WhatsAppNotificationService::maskPhoneNumber($rawNumber);
        $provider = (string) config('whatsapp.provider', 'fonnte');
        $note = (string) ($this->option('note') ?? 'Pengujian CLI WhatsApp IT Support APG CRM');

        $this->info('Memulai pengujian notifikasi WhatsApp...');
        $this->table(
            ['Parameter', 'Nilai'],
            [
                ['Status Fitur', 'Aktif'],
                ['Provider', strtoupper($provider)],
                ['Nomor Recipient (Masked)', $maskedNumber],
                ['Pesan Uji', $note],
            ]
        );

        $notification = $service->notifyItSupport(
            eventType: 'test',
            ticket: null,
            variables: [
                'time' => now()->format('d/m/Y H:i:s'),
                'note' => WhatsAppNotificationService::sanitizeText($note, 100),
            ],
            deduplicationKey: 'cli-test:'.now()->timestamp.':'.uniqid()
        );

        if (! $notification) {
            $this->error('Gagal membuat outbox notifikasi WhatsApp.');

            return static::FAILURE;
        }

        $this->info("Berhasil membuat outbox notifikasi [ID: {$notification->id}]. Status saat ini: {$notification->status}.");

        return static::SUCCESS;
    }
}
