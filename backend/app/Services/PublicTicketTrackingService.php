<?php

namespace App\Services;

use App\Models\PublicTicketTrackingToken;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PublicTicketTrackingService
{
    private const DOMAIN = 'apg-crm:public-ticket-tracking:v1';

    /** @return array{record: PublicTicketTrackingToken, raw_token: string, tracking_url: string} */
    public function create(Ticket $ticket, ?User $creator = null): array
    {
        $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
        if ($ticket->publicTrackingTokens()
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists()) {
            throw new RuntimeException('The ticket already has active public tracking access.');
        }
        $generation = ((int) $ticket->publicTrackingTokens()->max('generation')) + 1;
        $nonce = bin2hex(random_bytes(32));
        $keyVersion = (int) config('public_tracking.key_version', 1);
        if ($keyVersion < 1) {
            throw new RuntimeException('PUBLIC_TICKET_TRACKING_KEY_VERSION must be a positive integer.');
        }
        $rawToken = $this->deriveRawToken($nonce, $ticket->id, $generation, $keyVersion);
        $expiryDays = config('public_tracking.expiry_days');
        if (filled($expiryDays) && (filter_var($expiryDays, FILTER_VALIDATE_INT) === false || (int) $expiryDays < 1)) {
            throw new RuntimeException('PUBLIC_TICKET_TRACKING_EXPIRY_DAYS must be a positive integer or empty.');
        }

        $record = $ticket->publicTrackingTokens()->create([
            'derivation_nonce' => $nonce,
            'generation' => $generation,
            'key_version' => $keyVersion,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => filled($expiryDays) ? now()->addDays((int) $expiryDays) : null,
            'created_by' => $creator?->id,
        ]);

        return ['record' => $record, 'raw_token' => $rawToken, 'tracking_url' => $this->trackingUrl($rawToken)];
    }

    /** @return array{record: PublicTicketTrackingToken, raw_token: string, tracking_url: string} */
    public function receipt(PublicTicketTrackingToken $record): array
    {
        $rawToken = $this->deriveRawToken($record->derivation_nonce, $record->ticket_id, $record->generation, $record->key_version);
        if (! hash_equals($record->token_hash, hash('sha256', $rawToken))) {
            throw new RuntimeException('Public tracking token derivation failed integrity validation.');
        }

        return ['record' => $record, 'raw_token' => $rawToken, 'tracking_url' => $this->trackingUrl($rawToken)];
    }

    public function lookup(string $rawToken): Ticket
    {
        if (! preg_match('/\A[A-Za-z0-9_-]{43}\z/', $rawToken)) {
            throw new NotFoundHttpException;
        }

        $record = PublicTicketTrackingToken::query()
            ->where('token_hash', hash('sha256', $rawToken))
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if (! $record) {
            throw new NotFoundHttpException;
        }

        $record->forceFill(['last_used_at' => now()])->save();

        return $record->ticket()->with([
            'branch:id,name',
            'category:id,name',
            'histories:id,ticket_id,to_status,created_at',
            'comments' => fn ($query) => $query
                ->select(['id', 'ticket_id', 'type', 'comment', 'created_at'])
                ->where('is_internal', false)
                ->whereNull('defect_id')
                ->whereNull('uat_finding_id')
                ->whereIn('type', ['info_request', 'requester_summary', 'approval_summary', 'rejection_reason', 'cancellation_reason'])
                ->oldest('created_at')->oldest('id'),
        ])->firstOrFail();
    }

    public function revoke(Ticket $ticket, User $actor, string $reason): void
    {
        DB::transaction(function () use ($ticket, $actor, $reason): void {
            Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $ticket->publicTrackingTokens()->whereNull('revoked_at')->update([
                'revoked_at' => now(),
                'revoked_by' => $actor->id,
                'updated_at' => now(),
            ]);
            $this->audit($ticket, $actor, 'public_tracking_revoked', $reason);
        });
    }

    /** @return array{record: PublicTicketTrackingToken, raw_token: string, tracking_url: string} */
    public function rotate(Ticket $ticket, User $actor, string $reason): array
    {
        return DB::transaction(function () use ($ticket, $actor, $reason): array {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $ticket->publicTrackingTokens()->whereNull('revoked_at')->update([
                'revoked_at' => now(),
                'revoked_by' => $actor->id,
                'updated_at' => now(),
            ]);
            $issued = $this->create($ticket, $actor);
            $this->audit($ticket, $actor, 'public_tracking_rotated', $reason);

            return $issued;
        });
    }

    public function deriveRawToken(string $nonce, int $ticketId, int $generation, int $keyVersion): string
    {
        if (! preg_match('/\A[0-9a-f]{64}\z/', $nonce)) {
            throw new RuntimeException('Invalid public tracking derivation nonce.');
        }

        $payload = self::DOMAIN."\0".hex2bin($nonce)."\0{$ticketId}\0{$generation}\0{$keyVersion}";
        $bytes = hash_hmac('sha256', $payload, $this->secret(), true);

        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public function trackingUrl(string $rawToken): string
    {
        $frontendUrl = rtrim(trim(explode(',', (string) config('public_tracking.frontend_url'))[0]), '/');
        if (! filter_var($frontendUrl, FILTER_VALIDATE_URL) || ! Str::startsWith($frontendUrl, ['http://', 'https://'])) {
            throw new RuntimeException('FRONTEND_URL must be a valid HTTP(S) URL for public ticket tracking.');
        }

        return $frontendUrl.'/'.trim((string) config('public_tracking.frontend_path'), '/').'/'.$rawToken;
    }

    private function secret(): string
    {
        $configured = config('public_tracking.key');
        if (blank($configured) && ! app()->environment('production')) {
            $configured = config('app.key');
        }
        if (blank($configured)) {
            throw new RuntimeException('PUBLIC_TICKET_TRACKING_KEY is required.');
        }

        $secret = Str::startsWith($configured, 'base64:')
            ? base64_decode(Str::after($configured, 'base64:'), true)
            : $configured;
        if (! is_string($secret) || strlen($secret) < 32) {
            throw new RuntimeException('PUBLIC_TICKET_TRACKING_KEY must contain at least 256 bits of key material.');
        }

        return hash_hmac('sha256', self::DOMAIN, $secret, true);
    }

    private function audit(Ticket $ticket, User $actor, string $action, string $reason): void
    {
        $status = $ticket->current_workflow_stage ?: $ticket->status->value;
        $reason = preg_replace('/https?:\/\/\S+|(?<![A-Za-z0-9_-])[A-Za-z0-9_-]{43}(?![A-Za-z0-9_-])|\b[0-9a-f]{64}\b/i', '[redacted]', $reason) ?? '[redacted]';
        $ticket->histories()->create([
            'from_status' => $status,
            'to_status' => $status,
            'action' => $action,
            'actor_id' => $actor->id,
            'actor_role' => $actor->role?->key ?? 'unknown',
            'notes' => $reason,
        ]);
    }
}
