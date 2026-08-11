<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\WhatsAppGateway;
use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\WhatsAppNotification;
use App\Services\WhatsAppNotificationService;
use App\Services\WhatsAppNotificationSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class WhatsAppManagementController extends Controller
{
    public function settings(WhatsAppNotificationSettings $settings): JsonResponse
    {
        $effective = $settings->effective();
        $enabled = $effective['enabled'];
        $configured = $this->configurationReady();
        $stats = collect(['queued', 'processing', 'sent', 'pending', 'failed', 'invalid', 'expired'])
            ->mapWithKeys(fn (string $status): array => [$status => WhatsAppNotification::query()->where('status', $status)->count()]);

        return response()->json([
            'enabled' => $enabled,
            'ready' => $enabled && $configured && config('whatsapp.provider') === 'fonnte',
            'driver' => 'fonnte',
            'provider' => 'FONNTE',
            'delivery_mode' => $enabled ? 'live' : 'disabled',
            'status_message' => ! $enabled ? 'Notifikasi WhatsApp dinonaktifkan oleh pengaturan efektif.'
                : ($configured ? 'Fonnte siap memproses notifikasi.' : 'Konfigurasi Fonnte belum lengkap.'),
            'base_url' => config('whatsapp.fonnte.base_url'),
            'country_code' => config('whatsapp.fonnte.country_code'),
            'connect_only' => config('whatsapp.fonnte.connect_only'),
            'timeout' => config('whatsapp.fonnte.timeout'),
            'retry_times' => config('whatsapp.fonnte.retry_times'),
            'it_support_number_masked' => WhatsAppNotificationService::maskPhoneNumber(config('whatsapp.fonnte.it_support_number')),
            'token_configured' => filled(config('whatsapp.fonnte.token')),
            'webhook_secret_configured' => filled(config('whatsapp.fonnte.webhook_secret')),
            'last_sent_at' => WhatsAppNotification::query()->whereNotNull('sent_at')->max('sent_at'),
            'stats' => ['total' => WhatsAppNotification::query()->count(), ...$stats->all()],
            'events' => $effective['events'],
            'config_defaults' => $effective['config_defaults'],
            'has_database_override' => $effective['has_database_override'],
            'templates' => config('whatsapp.templates'),
        ]);
    }

    public function updateSettings(Request $request, WhatsAppNotificationSettings $settings): JsonResponse
    {
        $eventKeys = array_keys(config('whatsapp.events', []));
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'events' => ['sometimes', 'array:'.implode(',', $eventKeys)],
            'events.*' => ['boolean'],
        ]);
        if ($validated['enabled'] && ! config('whatsapp.enabled')) {
            return response()->json(['message' => 'WhatsApp dinonaktifkan oleh environment server.'], 422);
        }
        if ($validated['enabled'] && ! $this->configurationReady()) {
            return response()->json(['message' => 'Konfigurasi Fonnte belum lengkap atau tidak aman.'], 422);
        }
        $settings->update($validated['enabled'], $validated['events'] ?? []);

        return $this->settings($settings);
    }

    public function deviceProfile(WhatsAppGateway $gateway, WhatsAppNotificationSettings $settings): JsonResponse
    {
        if (! $settings->enabled()) {
            return response()->json(['success' => false, 'message' => 'Notifikasi WhatsApp dinonaktifkan.'], 422);
        }
        $result = $gateway->getDeviceProfile();

        $profile = collect($result['response'] ?? [])->only(['device', 'device_status', 'status', 'package', 'quota', 'expired', 'messages'])->all();
        if (isset($profile['device'])) {
            $profile['device'] = WhatsAppNotificationService::maskPhoneNumber((string) $profile['device']);
        }

        return response()->json(['success' => (bool) ($result['success'] ?? false), 'data' => $profile,
            'status' => $result['status'] ?? null, 'message' => $result['error_message'] ?? null], ($result['success'] ?? false) ? 200 : 502);
    }

    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate(['status' => ['nullable', 'in:queued,processing,sent,pending,failed,invalid,expired'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $page = WhatsAppNotification::query()->with('ticket:id,ticket_number')->when($validated['status'] ?? null,
            fn ($query, $status) => $query->where('status', $status))->latest()->paginate($validated['per_page'] ?? 25);
        $page->through(fn (WhatsAppNotification $row): array => [
            'id' => $row->id, 'event_type' => $row->event_type, 'ticket_number' => $row->ticket?->ticket_number,
            'recipient_role' => $row->recipient_role, 'recipient_type' => $row->recipient_type,
            'recipient_masked' => '****'.$row->recipient_last_four, 'template_name' => $row->template_name,
            'provider' => $row->provider,
            'status' => $row->status, 'attempts' => $row->attempts, 'failure_code' => $row->failure_code,
            'failure_message' => $row->failure_message_safe, 'queued_at' => $row->queued_at?->toIso8601String(),
            'sent_at' => $row->sent_at?->toIso8601String(), 'failed_at' => $row->failed_at?->toIso8601String(),
        ]);

        return response()->json($page);
    }

    public function retry(WhatsAppNotification $notification): JsonResponse
    {
        $updated = WhatsAppNotification::query()->whereKey($notification->id)->whereIn('status', ['failed', 'invalid', 'expired'])
            ->update([
                'status' => 'queued', 'queued_at' => now(), 'processing_at' => null, 'sent_at' => null,
                'failed_at' => null, 'expired_at' => null, 'provider_message_id' => null,
                'provider_message_ids' => null, 'provider_request_id' => null, 'provider_response' => null,
                'failure_code' => null, 'failure_message_safe' => null, 'attempts' => 0,
            ]);
        if (! $updated) {
            return response()->json(['success' => false, 'message' => 'Notifikasi tidak dapat diulang pada status saat ini.'], 409);
        }
        try {
            SendWhatsAppNotificationJob::dispatch($notification->id)->onQueue((string) config('whatsapp.queue'));
        } catch (Throwable $exception) {
            WhatsAppNotification::query()->whereKey($notification->id)->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_code' => 'QUEUE_DISPATCH_FAILED',
                'failure_message_safe' => 'Pesan gagal dimasukkan ke antrean pengiriman.',
            ]);
            Log::error('WhatsApp manual retry dispatch failed.', [
                'notification_id' => $notification->id,
                'exception_class' => $exception::class,
            ]);

            return response()->json(['success' => false, 'message' => 'Notifikasi gagal dimasukkan ke antrean.'], 503);
        }

        return response()->json(['success' => true, 'data' => ['notification_id' => $notification->id, 'status' => 'queued']]);
    }

    public function sendTestMessage(Request $request, WhatsAppNotificationService $service, WhatsAppNotificationSettings $settings): JsonResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:100']]);
        if (! $settings->enabled() || ! $this->configurationReady()) {
            return response()->json(['success' => false, 'message' => 'Konfigurasi Fonnte belum siap.'], 422);
        }
        $notification = $service->notifyItSupport('test', null, ['time' => now()->format('d/m/Y H:i:s'),
            'note' => $validated['note'] ?? 'Pengujian notifikasi'], 'test:'.(string) str()->uuid());
        if (! $notification) {
            return response()->json(['success' => false, 'message' => 'Pesan uji tidak dapat dibuat.'], 422);
        }
        Log::info('WhatsApp test message queued.', ['user_id' => $request->user()?->id, 'notification_id' => $notification->id,
            'recipient_masked' => WhatsAppNotificationService::maskPhoneNumber(config('whatsapp.fonnte.it_support_number'))]);

        return response()->json(['success' => true, 'message' => 'Pesan uji Fonnte masuk ke antrean untuk nomor IT Support ('.WhatsAppNotificationService::maskPhoneNumber(config('whatsapp.fonnte.it_support_number')).').',
            'data' => ['notification_id' => $notification->id, 'recipient_masked' => WhatsAppNotificationService::maskPhoneNumber(config('whatsapp.fonnte.it_support_number')), 'status' => 'queued']]);
    }

    private function configurationReady(): bool
    {
        $secret = (string) config('whatsapp.fonnte.webhook_secret');

        return config('whatsapp.provider') === 'fonnte'
            && filled(config('whatsapp.fonnte.token'))
            && WhatsAppNotificationService::normalizePhoneNumber(config('whatsapp.fonnte.it_support_number')) !== null
            && strlen($secret) >= 32;
    }
}
