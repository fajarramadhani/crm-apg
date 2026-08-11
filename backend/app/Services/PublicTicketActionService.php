<?php

namespace App\Services;

use App\Contracts\PublicHistoryOtpDelivery;
use App\Enums\RequesterConfirmationStatus;
use App\Enums\TicketStatus;
use App\Events\PublicRequesterActionCompleted;
use App\Exceptions\IdempotencyConflict;
use App\Exceptions\PublicActionAccessDenied;
use App\Exceptions\PublicActionVerificationFailed;
use App\Models\PublicTicketActionAccessToken;
use App\Models\PublicTicketActionChallenge;
use App\Models\PublicTicketActionIdempotency;
use App\Models\PublicTicketTrackingToken;
use App\Models\Ticket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

final class PublicTicketActionService
{
    private const IDENTITY_DOMAIN = 'apg-crm:public-action:identity:v1';

    private const OTP_DOMAIN = 'apg-crm:public-action:otp:v1';

    private const CHALLENGE_DOMAIN = 'apg-crm:public-action:challenge:v1';

    public function __construct(
        private PublicHistoryOtpDelivery $delivery,
        private TicketTransitionService $transitions,
        private TicketRequesterConfirmationService $confirmations,
        private PublicTicketStatusMapper $statusMapper,
        private TicketAttachmentService $attachments,
    ) {}

    public function rateFingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, $this->secret('identity_key'));
    }

    /** @return array<string, mixed> */
    public function available(Ticket $ticket): array
    {
        $action = $this->actionFor($ticket);
        $status = $this->statusMapper->map($ticket->current_workflow_stage ?: $ticket->status);

        return [
            'type' => $action,
            'label' => match ($action) {
                'uat' => 'Lakukan Pengujian',
                'confirmation' => 'Konfirmasi Penyelesaian',
                default => null,
            },
            'current_public_status' => $status,
            'verification_required' => $action !== null,
            'notes_required' => $action === null ? null : ['accepted' => false, 'rejected' => true],
            'attachment_constraints' => $action === 'uat' ? [
                'optional' => true,
                'max_files' => 10,
                'max_size_mb' => 10,
                'extensions' => ['png', 'jpg', 'jpeg', 'pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx'],
            ] : null,
        ];
    }

    /** @return array{challenge_token: string, masked_destination: string, expires_at: string, resend_available_at: string} */
    public function challenge(Ticket $ticket, PublicTicketTrackingToken $tracking, string $action, string $email): array
    {
        $identityHash = $this->identityHash($email);
        $nonce = bin2hex(random_bytes(32));
        $rawToken = $this->deriveChallengeToken($nonce, $ticket->id, $tracking->id, $action, $identityHash);
        $tokenHash = hash('sha256', $rawToken);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiryMinutes = $this->positiveConfig('otp_expiry_minutes');
        $cooldownSeconds = $this->positiveConfig('resend_cooldown_seconds');
        $eligible = $this->actionFor($ticket) === $action && hash_equals($this->identityHash((string) $ticket->requester_email), $identityHash);

        $challenge = DB::transaction(function () use ($ticket, $tracking, $action, $email, $identityHash, $nonce, $tokenHash, $code, $expiryMinutes, $cooldownSeconds, $eligible): PublicTicketActionChallenge {
            PublicTicketTrackingToken::query()->whereKey($tracking->id)->lockForUpdate()->firstOrFail();
            $existing = PublicTicketActionChallenge::query()
                ->where('public_tracking_token_id', $tracking->id)->where('action', $action)
                ->where('identity_hash', $identityHash)->whereNull('superseded_at')
                ->latest('id')->lockForUpdate()->first();
            if ($existing && ! $existing->consumed_at && $existing->expires_at->isFuture() && $existing->resend_available_at->isFuture()) {
                return $existing;
            }
            PublicTicketActionChallenge::query()
                ->where('public_tracking_token_id', $tracking->id)->where('action', $action)
                ->where('identity_hash', $identityHash)->whereNull('superseded_at')
                ->update(['superseded_at' => now(), 'updated_at' => now()]);

            return PublicTicketActionChallenge::query()->create([
                'derivation_nonce' => $nonce,
                'challenge_token_hash' => $tokenHash,
                'ticket_id' => $ticket->id,
                'public_tracking_token_id' => $tracking->id,
                'branch_id' => $ticket->branch_id,
                'identity_hash' => $identityHash,
                'identity_ciphertext' => Crypt::encryptString($email),
                'action' => $action,
                'status_hash' => $this->statusHash($ticket),
                'otp_hash' => Hash::make($this->otpMaterial($tokenHash, $code)),
                'expires_at' => now()->addMinutes($expiryMinutes),
                'attempts_remaining' => $this->positiveConfig('max_attempts'),
                'resend_available_at' => now()->addSeconds($cooldownSeconds),
                'sent_at' => $eligible ? now() : null,
            ]);
        });

        $responseToken = hash_equals($challenge->challenge_token_hash, $tokenHash)
            ? $rawToken
            : $this->deriveChallengeToken($challenge->derivation_nonce, $challenge->ticket_id, $challenge->public_tracking_token_id, $challenge->action, $challenge->identity_hash);
        if ($eligible && hash_equals($challenge->challenge_token_hash, $tokenHash)) {
            try {
                $this->delivery->deliver('email', $email, $code, $expiryMinutes);
            } catch (Throwable) {
                $challenge->update(['superseded_at' => now()]);
                // Keep the public contract generic when delivery is unavailable.
            }
        }

        return [
            'challenge_token' => $responseToken,
            'masked_destination' => $this->maskEmail($email),
            'expires_at' => $challenge->expires_at->toISOString(),
            'resend_available_at' => $challenge->resend_available_at->toISOString(),
        ];
    }

    /** @return array{access_token: string, expires_at: string} */
    public function verify(Ticket $ticket, PublicTicketTrackingToken $tracking, string $action, string $rawChallenge, string $code): array
    {
        if (! $this->validToken($rawChallenge)) {
            $this->dummyCheck($code);
            throw new PublicActionVerificationFailed;
        }
        $challengeHash = hash('sha256', $rawChallenge);
        $result = DB::transaction(function () use ($ticket, $tracking, $action, $challengeHash, $code): ?array {
            $challenge = PublicTicketActionChallenge::query()->where('challenge_token_hash', $challengeHash)->lockForUpdate()->first();
            $invalid = ! $challenge || $challenge->ticket_id !== $ticket->id || $challenge->public_tracking_token_id !== $tracking->id
                || $challenge->action !== $action || ! $challenge->sent_at || $challenge->expires_at->isPast()
                || $challenge->consumed_at || $challenge->superseded_at || $challenge->attempts_remaining < 1
                || ! hash_equals($challenge->status_hash, $this->statusHash($ticket)) || $this->actionFor($ticket) !== $action;
            if ($invalid) {
                $this->dummyCheck($code);

                return null;
            }
            if (! Hash::check($this->otpMaterial($challengeHash, $code), $challenge->otp_hash)) {
                $challenge->decrement('attempts_remaining');

                return null;
            }
            $challenge->update(['consumed_at' => now()]);
            $rawAccess = $this->randomToken();
            $access = PublicTicketActionAccessToken::query()->create([
                'token_hash' => hash('sha256', $rawAccess),
                'ticket_id' => $ticket->id,
                'public_tracking_token_id' => $tracking->id,
                'branch_id' => $challenge->branch_id,
                'identity_hash' => $challenge->identity_hash,
                'action' => $action,
                'status_hash' => $challenge->status_hash,
                'expires_at' => now()->addMinutes($this->positiveConfig('action_access_ttl_minutes')),
            ]);

            return ['access_token' => $rawAccess, 'expires_at' => $access->expires_at->toISOString()];
        });
        if ($result === null) {
            throw new PublicActionVerificationFailed;
        }

        return $result;
    }

    public function authenticate(string $rawTracking, ?string $rawAccess): PublicTicketActionAccessToken
    {
        if (! is_string($rawAccess) || ! $this->validToken($rawAccess)) {
            throw new PublicActionAccessDenied;
        }
        $tracking = $this->activeTracking($rawTracking);
        $access = PublicTicketActionAccessToken::query()->where('token_hash', hash('sha256', $rawAccess))
            ->where('public_tracking_token_id', $tracking->id)->whereNull('revoked_at')
            ->where('expires_at', '>', now())->first();
        if (! $access) {
            throw new PublicActionAccessDenied;
        }
        $access->update(['last_used_at' => now()]);

        return $access;
    }

    public function revoke(PublicTicketActionAccessToken $access): void
    {
        $access->update(['revoked_at' => now()]);
    }

    /** @param array<int, UploadedFile> $files
     * @return array{result: array<string, mixed>, replay: bool}
     */
    public function mutate(Ticket $ticket, PublicTicketActionAccessToken $access, string $action, string $outcome, ?string $notes, array $files, string $idempotencyKey): array
    {
        $notes = $this->plainText($notes);
        $requestHash = $this->requestHash($outcome, $notes, $files);
        $storedPaths = [];
        try {
            $processed = DB::transaction(function () use ($ticket, $access, $action, $outcome, $notes, $files, $idempotencyKey, $requestHash, &$storedPaths): array {
                $lockedAccess = PublicTicketActionAccessToken::query()->whereKey($access->id)->lockForUpdate()->firstOrFail();
                $idempotency = PublicTicketActionIdempotency::query()
                    ->where('public_action_access_token_id', $lockedAccess->id)->where('action', $action)
                    ->where('key_hash', hash('sha256', $idempotencyKey))->lockForUpdate()->first();
                if ($idempotency?->expires_at->isPast()) {
                    $idempotency->delete();
                    $idempotency = null;
                }
                if ($idempotency) {
                    if (! hash_equals($idempotency->request_hash, $requestHash)) {
                        throw new IdempotencyConflict('payload_mismatch');
                    }
                    if ($idempotency->response_status === null) {
                        throw new IdempotencyConflict('in_progress');
                    }

                    $storedResult = $idempotency->result;

                    return ['result' => [
                        'action' => $storedResult['action'],
                        'outcome' => $storedResult['outcome'],
                        'status' => $storedResult['status'],
                    ], 'replay' => true];
                }
                if ($lockedAccess->consumed_at || $lockedAccess->revoked_at || $lockedAccess->expires_at->isPast() || $lockedAccess->action !== $action) {
                    throw new PublicActionAccessDenied;
                }
                $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
                $tracking = PublicTicketTrackingToken::query()->whereKey($lockedAccess->public_tracking_token_id)->lockForUpdate()->first();
                if (! $tracking || $tracking->ticket_id !== $locked->id || $tracking->revoked_at || $tracking->expires_at?->isPast()
                    || (int) $locked->publicTrackingTokens()->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->max('generation') !== (int) $tracking->generation
                    || ! hash_equals($lockedAccess->status_hash, $this->statusHash($locked)) || $this->actionFor($locked) !== $action) {
                    throw new PublicActionAccessDenied;
                }
                if ($action === 'confirmation' && $files !== []) {
                    throw new ConflictHttpException('Confirmation evidence is not supported.');
                }
                $idempotency = PublicTicketActionIdempotency::query()->create([
                    'ticket_id' => $locked->id,
                    'public_tracking_token_id' => $tracking->id,
                    'public_action_access_token_id' => $lockedAccess->id,
                    'action' => $action,
                    'key_hash' => hash('sha256', $idempotencyKey),
                    'request_hash' => $requestHash,
                    'expires_at' => $lockedAccess->expires_at,
                ]);

                if ($action === 'uat') {
                    $this->processUat($locked, $outcome, $notes);
                    foreach ($files as $file) {
                        $storedPaths[] = $this->storeEvidence($locked, $file);
                    }
                } else {
                    $this->confirmations->respondToConfirmation($locked, null, [
                        'status' => $outcome === 'accepted' ? RequesterConfirmationStatus::Accepted->value : RequesterConfirmationStatus::Rejected->value,
                        'notes' => $outcome === 'accepted' ? $notes : null,
                        'rejection_reason' => $outcome === 'rejected' ? $notes : null,
                    ], false);
                    if ($notes) {
                        $locked->comments()->create(['user_id' => null, 'type' => 'confirmation_feedback', 'comment' => $notes, 'is_internal' => false]);
                    }
                }
                $fresh = $locked->fresh();
                $result = ['action' => $action, 'outcome' => $outcome, 'status' => $this->statusMapper->map($fresh->status)];
                $idempotency->update(['result' => $result, 'response_status' => 200]);
                $lockedAccess->update(['consumed_at' => now()]);

                return ['result' => $result, 'replay' => false, 'ticket' => $fresh];
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
            throw $exception;
        }
        if (! $processed['replay']) {
            try {
                PublicRequesterActionCompleted::dispatch($processed['ticket'], $action, $outcome);
            } catch (Throwable $exception) {
                report($exception);
            }
            unset($processed['ticket']);
        }

        return $processed;
    }

    public function trackingRecord(string $rawTracking): PublicTicketTrackingToken
    {
        return $this->activeTracking($rawTracking);
    }

    private function processUat(Ticket $ticket, string $outcome, ?string $notes): void
    {
        if ($ticket->status !== TicketStatus::UatInProgress) {
            $from = $ticket->status;
            $this->transitions->publicPhaseTransition($ticket, $from, TicketStatus::UatInProgress, 'uat_started', 'UAT started by verified public requester.', function (Ticket $locked) use ($from): void {
                $locked->uat_started_at = now();
                $locked->uat_cycle_number = $from === TicketStatus::UatRetest ? ((int) $locked->uat_cycle_number) + 1 : max(1, (int) $locked->uat_cycle_number);
            });
            $ticket->refresh();
        }
        if ($notes) {
            $ticket->comments()->create(['user_id' => null, 'type' => 'uat_feedback', 'comment' => $notes, 'is_internal' => false]);
        }
        if ($outcome === 'accepted') {
            $this->transitions->publicPhaseTransition($ticket, TicketStatus::UatInProgress, TicketStatus::UatApproved, 'uat_approved', $notes, function (Ticket $locked): void {
                $locked->uat_completed_at = now();
                $locked->latest_uat_result = 'accepted';
                $locked->uat_approved_at = now();
            });
        } else {
            $this->transitions->publicPhaseTransition($ticket, TicketStatus::UatInProgress, TicketStatus::UatFailed, 'uat_failed', $notes, fn (Ticket $locked) => $locked->latest_uat_result = 'rejected');
            $ticket->refresh();
            $this->transitions->publicPhaseTransition($ticket, TicketStatus::UatFailed, TicketStatus::DevelopmentInProgress, 'uat_rework_started', $notes, function (Ticket $locked): void {
                $locked->progress_percentage = min(90, (int) $locked->progress_percentage);
                $locked->latest_progress_at = now();
            });
        }
    }

    /** @return array{string, string} */
    private function storeEvidence(Ticket $ticket, UploadedFile $file): array
    {
        $attachment = $this->attachments->store(
            $ticket, $file, null, 'uat_evidence', 'requester', directory: "tickets/{$ticket->id}/uat-evidence",
        );

        return [$attachment->disk, $attachment->path];
    }

    private function activeTracking(string $rawTracking): PublicTicketTrackingToken
    {
        if (! $this->validToken($rawTracking)) {
            throw new PublicActionAccessDenied;
        }
        $record = PublicTicketTrackingToken::query()->where('token_hash', hash('sha256', $rawTracking))
            ->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->first();
        if (! $record || (int) $record->generation !== (int) $record->ticket->publicTrackingTokens()->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->max('generation')) {
            throw new PublicActionAccessDenied;
        }

        return $record;
    }

    private function actionFor(Ticket $ticket): ?string
    {
        if ($ticket->submission_source !== 'public_form' || $ticket->requester_id !== null) {
            return null;
        }

        return match ($ticket->status) {
            TicketStatus::ReadyForUat, TicketStatus::UatAssignment, TicketStatus::UatInProgress, TicketStatus::UatRetest => 'uat',
            TicketStatus::AwaitingRequesterConfirmation => $ticket->requesterConfirmations()->whereNotNull('responded_at')->exists()
                ? null : 'confirmation',
            default => null,
        };
    }

    private function statusHash(Ticket $ticket): string
    {
        return hash('sha256', $ticket->status->value);
    }

    /** @param array<int, UploadedFile> $files */
    private function requestHash(string $outcome, ?string $notes, array $files): string
    {
        $fileData = array_map(fn (UploadedFile $file): array => [
            'name' => $file->getClientOriginalName(), 'size' => $file->getSize(), 'mime' => $file->getMimeType(),
            'hash' => hash_file('sha256', $file->getRealPath()),
        ], $files);

        return hash('sha256', json_encode(['outcome' => $outcome, 'notes' => $notes, 'files' => $fileData], JSON_THROW_ON_ERROR));
    }

    private function plainText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1\s*>/is', '', $value) ?? '';
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return trim($value);
    }

    private function identityHash(string $email): string
    {
        return hash_hmac('sha256', self::IDENTITY_DOMAIN."\0email\0{$email}", $this->secret('identity_key'));
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).str_repeat('*', max(3, mb_strlen($local) - 1)).'@'.$domain;
    }

    private function otpMaterial(string $challengeHash, string $code): string
    {
        return hash_hmac('sha256', self::OTP_DOMAIN."\0{$challengeHash}\0{$code}", $this->secret('otp_pepper'));
    }

    private function deriveChallengeToken(string $nonce, int $ticketId, int $trackingId, string $action, string $identityHash): string
    {
        $bytes = hash_hmac('sha256', self::CHALLENGE_DOMAIN."\0".hex2bin($nonce)."\0{$ticketId}\0{$trackingId}\0{$action}\0{$identityHash}", $this->secret('identity_key'), true);

        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function secret(string $key): string
    {
        $configured = config("public_history.{$key}") ?: (! app()->environment('production') ? config('app.key') : null);
        $decoded = is_string($configured) && Str::startsWith($configured, 'base64:') ? base64_decode(Str::after($configured, 'base64:'), true) : $configured;
        if (! is_string($decoded) || strlen($decoded) < 32) {
            throw new RuntimeException("Invalid public history {$key} secret.");
        }

        return $decoded;
    }

    private function positiveConfig(string $key): int
    {
        $value = config("public_history.{$key}");
        if (! is_int($value) || $value < 1) {
            throw new RuntimeException("Invalid public history {$key} configuration.");
        }

        return $value;
    }

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function validToken(string $token): bool
    {
        return preg_match('/\A[A-Za-z0-9_-]{43}\z/', $token) === 1;
    }

    private function dummyCheck(string $code): void
    {
        Hash::check($this->otpMaterial(str_repeat('0', 64), $code), Hash::make('invalid'));
    }
}
