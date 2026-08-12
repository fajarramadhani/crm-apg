<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGateway;
use App\Services\WhatsAppNotificationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class FonnteWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(private readonly array $config) {}

    public function sendMessage(string $recipient, string $message): array
    {
        return $this->post('/send', [
            'target' => $recipient,
            'message' => $message,
            'countryCode' => (string) $this->config['country_code'],
            'connectOnly' => ($this->config['connect_only'] ?? true) ? 'true' : 'false',
        ]);
    }

    public function validateNumber(string $recipient): array
    {
        return $this->post('/validate', [
            'target' => $recipient,
            'countryCode' => (string) $this->config['country_code'],
        ]);
    }

    public function getDeviceProfile(): array
    {
        return $this->post('/device');
    }

    /** @return array<string, mixed> */
    private function post(string $path, array $payload = []): array
    {
        if (app()->environment('production') && config('whatsapp.enabled')
            && (blank($this->config['token'] ?? null)
                || strlen((string) ($this->config['webhook_secret'] ?? '')) < 32
                || WhatsAppNotificationService::normalizePhoneNumber($this->config['it_support_number'] ?? null) === null)) {
            throw new RuntimeException('WhatsApp enabled in production but mandatory Fonnte configuration is missing.');
        }

        if (blank($this->config['token'] ?? null)) {
            return $this->failure('INVALID_TOKEN', 'Token Fonnte belum dikonfigurasi.', false);
        }

        // Sending is attempted once because a timeout may occur after Fonnte
        // accepted the message. Queue retries handle explicit provider rejects.
        $attempts = $path === '/send' ? 1 : max(1, (int) ($this->config['retry_times'] ?? 1));
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::timeout(max(1, (int) ($this->config['timeout'] ?? 15)))
                    ->acceptJson()
                    ->asMultipart()
                    ->withHeaders(['Authorization' => (string) $this->config['token']])
                    ->post(rtrim((string) $this->config['base_url'], '/').$path, $payload);
                $result = $this->result($response, $path === '/send');
                if (! ($result['retryable'] ?? false) || $attempt === $attempts) {
                    return $result;
                }
            } catch (ConnectionException $exception) {
                if ($attempt === $attempts) {
                    return $this->failure('DELIVERY_STATE_UNKNOWN', 'Respons Fonnte tidak diterima; status pengiriman tidak dapat dipastikan.', false);
                }
            } catch (Throwable) {
                return $this->failure('CLIENT_ERROR', 'Permintaan Fonnte gagal diproses.', false);
            }
        }

        return $this->failure('CONNECTION_ERROR', 'Fonnte tidak dapat dihubungi.', true);
    }

    /** @return array<string, mixed> */
    private function result(Response $response, bool $messageIdRequired): array
    {
        $json = $response->json();
        $data = is_array($json) ? $this->sanitize($json) : [];
        $successful = $response->successful() && filter_var($data['status'] ?? false, FILTER_VALIDATE_BOOL);

        if ($successful) {
            $ids = $data['id'] ?? [];
            $ids = is_array($ids) ? array_values(array_filter($ids, 'is_scalar')) : (filled($ids) ? [(string) $ids] : []);
            if ($messageIdRequired && $ids === []) {
                return $this->failure('INVALID_RESPONSE', 'Fonnte menerima permintaan tanpa ID pesan.', false, $data, $response->status());
            }

            return [
                'success' => true,
                'status' => strtolower((string) ($data['process'] ?? 'pending')) === 'sent' ? 'sent' : 'pending',
                'message_ids' => array_map('strval', $ids),
                'request_id' => isset($data['requestid']) ? (string) $data['requestid'] : null,
                'response' => $data,
                'retryable' => false,
            ];
        }

        $message = strtolower((string) ($data['reason'] ?? $data['detail'] ?? $data['message'] ?? 'Permintaan Fonnte ditolak.'));
        $code = match (true) {
            $response->status() === 401 || str_contains($message, 'token') => 'INVALID_TOKEN',
            str_contains($message, 'device') && (str_contains($message, 'disconnect') || str_contains($message, 'connect')) => 'DEVICE_DISCONNECTED',
            str_contains($message, 'invalid') && (str_contains($message, 'number') || str_contains($message, 'target')) => 'INVALID_NUMBER',
            str_contains($message, 'quota') || str_contains($message, 'limit') || str_contains($message, 'insufficient') => 'QUOTA_EXCEEDED',
            default => 'FONNTE_REJECTED',
        };
        $retryable = in_array($response->status(), [408, 425, 429], true) || $response->serverError();

        return $this->failure($code, $this->safeMessage($message), $retryable, $data, $response->status());
    }

    /** @return array<string, mixed> */
    private function failure(string $code, string $message, bool $retryable, array $response = [], ?int $httpStatus = null): array
    {
        return ['success' => false, 'status' => $code === 'INVALID_NUMBER' ? 'invalid' : 'failed', 'error_code' => $code,
            'error_message' => $message, 'retryable' => $retryable, 'response' => $response, 'http_status' => $httpStatus];
    }

    private function safeMessage(string $message): string
    {
        $token = (string) ($this->config['token'] ?? '');
        $message = $token !== '' ? str_replace($token, '[REDACTED]', $message) : $message;

        return mb_substr(preg_replace('/(token|authorization|secret)\s*[:=]\s*\S+/i', '$1=[REDACTED]', $message) ?? 'Permintaan Fonnte ditolak.', 0, 500);
    }

    private function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (preg_match('/token|authorization|secret/i', (string) $key)) {
                $data[$key] = '[REDACTED]';
            } elseif (preg_match('/target|phone|number|device/i', (string) $key) && is_scalar($value)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->safeMessage($value);
            }
        }

        return $data;
    }
}
