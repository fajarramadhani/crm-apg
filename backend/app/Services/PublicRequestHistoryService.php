<?php

namespace App\Services;

use App\Contracts\PublicHistoryOtpDelivery;
use App\Exceptions\PublicHistoryAccessDenied;
use App\Exceptions\PublicHistoryDeliveryFailed;
use App\Exceptions\PublicHistoryVerificationFailed;
use App\Models\Branch;
use App\Models\PublicRequestHistoryAccessToken;
use App\Models\PublicRequestHistoryChallenge;
use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class PublicRequestHistoryService
{
    private const IDENTITY_DOMAIN = 'apg-crm:public-history:identity:v1';

    private const OTP_DOMAIN = 'apg-crm:public-history:otp:v1';

    private const CHALLENGE_DOMAIN = 'apg-crm:public-history:challenge:v1';

    public function __construct(private PublicHistoryOtpDelivery $delivery) {}

    public function rateFingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, $this->secret('identity_key'));
    }

    /** @return array{challenge_token: string, masked_destination: string, expires_at: string, resend_available_at: string} */
    public function requestCode(string $email, int $branchId): array
    {
        $identityHash = $this->identityHash('email', $email);
        $nonce = bin2hex(random_bytes(32));
        $rawToken = $this->deriveChallengeToken($nonce, $branchId, $identityHash);
        $tokenHash = hash('sha256', $rawToken);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiryMinutes = $this->positiveConfig('otp_expiry_minutes');
        $cooldownSeconds = $this->positiveConfig('resend_cooldown_seconds');

        $challenge = DB::transaction(function () use ($branchId, $identityHash, $email, $nonce, $tokenHash, $code, $expiryMinutes, $cooldownSeconds) {
            Branch::query()->whereKey($branchId)->lockForUpdate()->firstOrFail();
            $existing = PublicRequestHistoryChallenge::query()
                ->where('branch_id', $branchId)->where('identity_type', 'email')
                ->where('identity_hash', $identityHash)->whereNull('superseded_at')
                ->latest('id')->lockForUpdate()->first();
            if ($existing && $existing->resend_available_at->isFuture()) {
                return $existing;
            }
            PublicRequestHistoryChallenge::query()
                ->where('branch_id', $branchId)->where('identity_type', 'email')
                ->where('identity_hash', $identityHash)->whereNull('superseded_at')
                ->update(['superseded_at' => now(), 'updated_at' => now()]);

            return PublicRequestHistoryChallenge::query()->create([
                'derivation_nonce' => $nonce,
                'challenge_token_hash' => $tokenHash,
                'branch_id' => $branchId,
                'identity_type' => 'email',
                'identity_hash' => $identityHash,
                'identity_ciphertext' => Crypt::encryptString($email),
                'otp_hash' => Hash::make($this->otpMaterial($tokenHash, $code)),
                'expires_at' => now()->addMinutes($expiryMinutes),
                'attempts_remaining' => $this->positiveConfig('max_attempts'),
                'resend_available_at' => now()->addSeconds($cooldownSeconds),
            ]);
        });

        if (! hash_equals($challenge->challenge_token_hash, $tokenHash)) {
            $replayedToken = $this->deriveChallengeToken(
                $challenge->derivation_nonce,
                $challenge->branch_id,
                $challenge->identity_hash,
            );

            return [
                'challenge_token' => $replayedToken,
                'masked_destination' => $this->maskEmail($email),
                'expires_at' => $challenge->expires_at->toISOString(),
                'resend_available_at' => $challenge->resend_available_at->toISOString(),
            ];
        }

        try {
            $this->delivery->deliver('email', $email, $code, $expiryMinutes);
            $challenge->forceFill(['sent_at' => now()])->save();
        } catch (Throwable) {
            $challenge->forceFill(['superseded_at' => now()])->save();
            throw new PublicHistoryDeliveryFailed;
        }

        return [
            'challenge_token' => $rawToken,
            'masked_destination' => $this->maskEmail($email),
            'expires_at' => $challenge->expires_at->toISOString(),
            'resend_available_at' => $challenge->resend_available_at->toISOString(),
        ];
    }

    /** @return array{access_token: string, expires_at: string} */
    public function verify(string $rawChallengeToken, string $code): array
    {
        if (! $this->validToken($rawChallengeToken)) {
            $this->dummyCheck($code);
            throw new PublicHistoryVerificationFailed;
        }
        $tokenHash = hash('sha256', $rawChallengeToken);

        $result = DB::transaction(function () use ($tokenHash, $code): ?array {
            $challenge = PublicRequestHistoryChallenge::query()
                ->where('challenge_token_hash', $tokenHash)->lockForUpdate()->first();
            $invalid = ! $challenge || ! $challenge->sent_at || $challenge->expires_at->isPast()
                || $challenge->consumed_at || $challenge->superseded_at || $challenge->attempts_remaining < 1;
            if ($invalid) {
                $this->dummyCheck($code);

                return null;
            }

            if (! Hash::check($this->otpMaterial($tokenHash, $code), $challenge->otp_hash)) {
                PublicRequestHistoryChallenge::query()->whereKey($challenge->id)
                    ->whereNull('consumed_at')->whereNull('superseded_at')
                    ->where('attempts_remaining', '>', 0)
                    ->decrement('attempts_remaining');

                return null;
            }

            $consumed = PublicRequestHistoryChallenge::query()->whereKey($challenge->id)
                ->whereNull('consumed_at')->whereNull('superseded_at')
                ->where('expires_at', '>', now())->where('attempts_remaining', '>', 0)
                ->update(['consumed_at' => now(), 'updated_at' => now()]);
            if ($consumed !== 1) {
                return null;
            }
            $rawAccessToken = $this->randomToken();
            $access = PublicRequestHistoryAccessToken::query()->create([
                'token_hash' => hash('sha256', $rawAccessToken),
                'branch_id' => $challenge->branch_id,
                'identity_type' => $challenge->identity_type,
                'identity_hash' => $challenge->identity_hash,
                'identity_ciphertext' => $challenge->identity_ciphertext,
                'expires_at' => now()->addMinutes($this->positiveConfig('access_ttl_minutes')),
            ]);

            return ['access_token' => $rawAccessToken, 'expires_at' => $access->expires_at->toISOString()];
        });

        if ($result === null) {
            throw new PublicHistoryVerificationFailed;
        }

        return $result;
    }

    public function authenticate(?string $rawToken): PublicRequestHistoryAccessToken
    {
        if (! is_string($rawToken) || ! $this->validToken($rawToken)) {
            throw new PublicHistoryAccessDenied;
        }
        $access = PublicRequestHistoryAccessToken::query()
            ->where('token_hash', hash('sha256', $rawToken))->whereNull('revoked_at')
            ->where('expires_at', '>', now())->first();
        if (! $access) {
            throw new PublicHistoryAccessDenied;
        }

        $identity = Crypt::decryptString($access->identity_ciphertext);
        if (! hash_equals($access->identity_hash, $this->identityHash($access->identity_type, $identity))) {
            throw new PublicHistoryAccessDenied;
        }
        $access->forceFill(['last_used_at' => now()])->save();

        return $access->setAttribute('verified_identity', $identity);
    }

    public function revoke(PublicRequestHistoryAccessToken $access): void
    {
        PublicRequestHistoryAccessToken::query()->whereKey($access->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    public function history(PublicRequestHistoryAccessToken $access, int $perPage): LengthAwarePaginator
    {
        return Ticket::query()->select([
            'id', 'ticket_number', 'title', 'branch_id', 'ticket_category_id', 'submitted_at',
            'status', 'current_workflow_stage', 'updated_at',
        ])->with(['branch:id,name', 'category:id,name'])
            ->where('submission_source', 'public_form')->whereNull('requester_id')
            ->where('branch_id', $access->branch_id)
            ->where('requester_email', $access->getAttribute('verified_identity'))
            ->latest('submitted_at')->latest('id')->paginate($perPage);
    }

    public function ownedTicket(PublicRequestHistoryAccessToken $access, string $ticketNumber): Ticket
    {
        return Ticket::query()->where('ticket_number', $ticketNumber)
            ->where('submission_source', 'public_form')->whereNull('requester_id')
            ->where('branch_id', $access->branch_id)
            ->where('requester_email', $access->getAttribute('verified_identity'))->firstOrFail();
    }

    private function identityHash(string $type, string $identity): string
    {
        return hash_hmac('sha256', self::IDENTITY_DOMAIN."\0{$type}\0{$identity}", $this->secret('identity_key'));
    }

    private function otpMaterial(string $challengeHash, string $code): string
    {
        return hash_hmac('sha256', self::OTP_DOMAIN."\0{$challengeHash}\0{$code}", $this->secret('otp_pepper'));
    }

    private function deriveChallengeToken(string $nonce, int $branchId, string $identityHash): string
    {
        if (! preg_match('/\A[0-9a-f]{64}\z/', $nonce)) {
            throw new RuntimeException('Invalid public history challenge nonce.');
        }
        $material = self::CHALLENGE_DOMAIN."\0".hex2bin($nonce)."\0{$branchId}\0{$identityHash}";
        $bytes = hash_hmac('sha256', $material, $this->secret('identity_key'), true);

        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function secret(string $key): string
    {
        $configured = config("public_history.{$key}");
        if (blank($configured) && ! app()->environment('production')) {
            $configured = config('app.key');
        }
        $decoded = is_string($configured) && Str::startsWith($configured, 'base64:')
            ? base64_decode(Str::after($configured, 'base64:'), true) : $configured;
        if (! is_string($decoded) || strlen($decoded) < 32) {
            throw new RuntimeException("PUBLIC_HISTORY_{$key} must contain at least 256 bits of key material.");
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

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, 1);

        return $visible.str_repeat('*', max(3, mb_strlen($local) - 1)).'@'.$domain;
    }

    private function dummyCheck(string $code): void
    {
        Hash::check($this->otpMaterial(str_repeat('0', 64), $code), Hash::make('invalid'));
    }
}
