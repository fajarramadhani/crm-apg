<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use JsonException;

final class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $configured = (string) config('whatsapp.fonnte.webhook_secret');
        $provided = (string) $request->header('X-Fonnte-Webhook-Secret', '');
        if (strlen($configured) < 32 || $provided === '' || ! hash_equals($configured, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (! $request->isJson()) {
            return response()->json(['message' => 'JSON body is required.'], 415);
        }
        if (strlen($request->getContent()) > 65536) {
            return response()->json(['message' => 'Webhook payload is too large.'], 413);
        }
        try {
            $payload = json_decode($request->getContent(), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['message' => 'Malformed JSON payload.'], 400);
        }
        if (! is_array($payload) || array_is_list($payload)) {
            return response()->json(['message' => 'Webhook payload must be a JSON object.'], 400);
        }
        if (is_string($payload['status'] ?? null)) {
            $payload['status'] = $this->normalizeStatus($payload['status']);
        }

        $validator = Validator::make($payload, [
            'id' => ['required', 'string', 'max:191'],
            'status' => ['required', 'string', 'in:pending,sent,invalid,failed,expired'],
            'state' => ['required', 'string', 'max:100'],
            'stateid' => ['required', 'string', 'max:100'],
            'device' => ['required', 'string', 'max:100'],
            'timestamp' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid webhook payload.', 'errors' => $validator->errors()], 422);
        }
        $payload = $validator->validated();
        $matched = DB::transaction(function () use ($payload): bool {
            $notifications = WhatsAppNotification::query()
                ->where('provider', 'fonnte')
                ->where(function ($query) use ($payload): void {
                    $query->where('provider_message_id', $payload['id'])
                        ->orWhereJsonContains('provider_message_ids', $payload['id']);
                })
                ->lockForUpdate()->limit(2)->get();
            if ($notifications->count() !== 1) {
                return false;
            }
            $notification = $notifications->firstOrFail();

            if (in_array($notification->status, ['sent', 'invalid', 'failed', 'expired'], true)) {
                return true;
            }

            $safeState = $this->sanitize($payload['state']);
            $response = ['id' => $payload['id'], 'status' => $payload['status'], 'state' => $safeState,
                'stateid' => $this->sanitize($payload['stateid']), 'device' => $this->sanitize($payload['device']),
                'timestamp' => $payload['timestamp'] ?? null, 'received_at' => now()->toIso8601String()];
            $attributes = ['status' => $payload['status'], 'provider_response' => $response];
            if ($payload['status'] === 'sent') {
                $attributes['sent_at'] = $notification->sent_at ?? now();
            } elseif (in_array($payload['status'], ['invalid', 'failed'], true)) {
                $attributes['failed_at'] = $notification->failed_at ?? now();
                $attributes['failure_code'] = 'FONNTE_'.strtoupper($payload['status']);
                $attributes['failure_message_safe'] = $safeState;
            } elseif ($payload['status'] === 'expired') {
                $attributes['expired_at'] = $notification->expired_at ?? now();
            }
            $notification->update($attributes);

            return true;
        });

        if (! $matched) {
            return response()->json(['status' => 'retry', 'matched' => false], 503);
        }

        return response()->json(['status' => 'ok', 'matched' => true]);
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'processing', 'pending', 'waiting' => 'pending',
            'sent' => 'sent',
            'invalid' => 'invalid',
            'expired' => 'expired',
            'failed', 'url unreachable' => 'failed',
            default => strtolower(trim($status)),
        };
    }

    private function sanitize(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';

        return mb_substr(preg_replace('/(token|authorization|secret)\s*[:=]\s*\S+/i', '$1=[REDACTED]', $value) ?? '', 0, 500);
    }
}
