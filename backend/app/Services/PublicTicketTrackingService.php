<?php

namespace App\Services;

use App\Exceptions\IdempotencyConflict;
use App\Models\IdempotencyRecord;
use App\Models\PublicTicketTrackingToken;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PublicTicketTrackingService
{
    private const DOMAIN = 'apg-crm:public-ticket-tracking:v1';

    private const MANAGEMENT_ENDPOINT = 'supervisor-it.public-tracking';

    public function __construct(private PublicTicketTrackingKeyRing $keyRing) {}

    /** @return array{record: PublicTicketTrackingToken, raw_token: string, tracking_url: string} */
    public function create(Ticket $ticket, ?User $creator = null): array
    {
        return DB::transaction(function () use ($ticket, $creator): array {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($this->activeRecord($ticket)) {
                throw new RuntimeException('The ticket already has active public tracking access.');
            }
            $issued = $this->issueLocked($ticket, $creator);
            $this->audit($ticket, $creator, 'public_tracking_created', 'Public tracking access created.', [
                'generation' => $issued['record']->generation,
                'expires_at' => $issued['record']->expires_at?->toISOString(),
            ]);

            return $issued;
        });
    }

    /** @return array{record: PublicTicketTrackingToken|null, state: string, tracking_url: string|null} */
    public function status(Ticket $ticket): array
    {
        $record = $ticket->publicTrackingTokens()->latest('generation')->first();
        $state = match (true) {
            $record === null => 'never_issued',
            $record->revoked_at !== null => 'revoked',
            $record->expires_at?->isPast() === true => 'expired',
            default => 'active',
        };
        $trackingUrl = null;
        if ($state === 'active') {
            try {
                $trackingUrl = $this->receipt($record)['tracking_url'];
            } catch (RuntimeException) {
                // The status remains visible, but a new link must be issued if reconstruction fails.
            }
        }

        return ['record' => $record, 'state' => $state, 'tracking_url' => $trackingUrl];
    }

    /** @return array{record: PublicTicketTrackingToken, raw_token: string, tracking_url: string} */
    public function issue(Ticket $ticket, User $actor, string $reason, string $idempotencyKey): array
    {
        return $this->manage($ticket, $actor, 'issue', $reason, $idempotencyKey);
    }

    private function issueLocked(Ticket $ticket, ?User $creator): array
    {
        $generation = ((int) $ticket->publicTrackingTokens()->max('generation')) + 1;
        $nonce = bin2hex(random_bytes(32));
        $keyVersion = $this->keyRing->activeVersion();
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
                ->whereIn('type', ['info_request', 'requester_summary', 'approval_summary', 'rejection_reason', 'cancellation_reason', 'uat_feedback', 'confirmation_feedback'])
                ->oldest('created_at')->oldest('id'),
        ])->firstOrFail();
    }

    public function revoke(Ticket $ticket, User $actor, string $reason, string $idempotencyKey): void
    {
        $this->manage($ticket, $actor, 'revoke', $reason, $idempotencyKey);
    }

    /** @return array{record: PublicTicketTrackingToken, raw_token: string, tracking_url: string} */
    public function rotate(Ticket $ticket, User $actor, string $reason, string $idempotencyKey): array
    {
        return $this->manage($ticket, $actor, 'rotate', $reason, $idempotencyKey);
    }

    /** @return array{record: PublicTicketTrackingToken, raw_token: string, tracking_url: string}|null */
    private function manage(Ticket $ticket, User $actor, string $action, string $reason, string $idempotencyKey): ?array
    {
        return DB::transaction(function () use ($ticket, $actor, $action, $reason, $idempotencyKey): ?array {
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $keyHash = hash('sha256', $idempotencyKey);
            $requestHash = hash('sha256', json_encode([
                'ticket_id' => $ticket->id,
                'action' => $action,
                'reason' => trim($reason),
            ], JSON_THROW_ON_ERROR));
            $idempotency = IdempotencyRecord::query()
                ->where('user_id', $actor->id)
                ->where('endpoint', self::MANAGEMENT_ENDPOINT)
                ->where('action', $action)
                ->where('key_hash', $keyHash)
                ->lockForUpdate()->first();
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
                if ($action === 'revoke') {
                    return null;
                }
                $record = PublicTicketTrackingToken::query()->findOrFail($idempotency->public_tracking_token_id);

                return $this->receipt($record);
            }

            $idempotency = IdempotencyRecord::query()->create([
                'user_id' => $actor->id,
                'endpoint' => self::MANAGEMENT_ENDPOINT,
                'action' => $action,
                'key_hash' => $keyHash,
                'request_hash' => $requestHash,
                'ticket_id' => $ticket->id,
                'expires_at' => now()->addDay(),
            ]);
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $active = $this->activeRecord($ticket);

            if ($action === 'issue') {
                if ($active) {
                    throw new HttpException(409, 'Public tracking access is already active.');
                }
                $issued = $this->issueLocked($ticket, $actor);
                $this->audit($ticket, $actor, 'public_tracking_created', $reason, [
                    'generation' => $issued['record']->generation,
                    'expires_at' => $issued['record']->expires_at?->toISOString(),
                ]);
            } elseif ($action === 'rotate') {
                if (! $active) {
                    throw new HttpException(409, 'No active public tracking access is available to rotate.');
                }
                $previousGeneration = $active->generation;
                $revokedCount = $ticket->publicTrackingTokens()->whereNull('revoked_at')->update([
                    'revoked_at' => now(), 'revoked_by' => $actor->id, 'updated_at' => now(),
                ]);
                $issued = $this->issueLocked($ticket, $actor);
                $this->audit($ticket, $actor, 'public_tracking_rotated', $reason, [
                    'previous_generation' => $previousGeneration,
                    'generation' => $issued['record']->generation,
                    'expires_at' => $issued['record']->expires_at?->toISOString(),
                    'revoked_count' => $revokedCount,
                ]);
            } else {
                $revokedCount = $ticket->publicTrackingTokens()->whereNull('revoked_at')->update([
                    'revoked_at' => now(), 'revoked_by' => $actor->id, 'updated_at' => now(),
                ]);
                if ($revokedCount > 0) {
                    $this->audit($ticket, $actor, 'public_tracking_revoked', $reason, ['revoked_count' => $revokedCount]);
                }
                $idempotency->update(['response_status' => 200]);

                return null;
            }

            $idempotency->update([
                'public_tracking_token_id' => $issued['record']->id,
                'response_status' => 200,
            ]);

            return $issued;
        });
    }

    private function activeRecord(Ticket $ticket): ?PublicTicketTrackingToken
    {
        return $ticket->publicTrackingTokens()->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('generation')->first();
    }

    public function deriveRawToken(string $nonce, int $ticketId, int $generation, int $keyVersion): string
    {
        if (! preg_match('/\A[0-9a-f]{64}\z/', $nonce)) {
            throw new RuntimeException('Invalid public tracking derivation nonce.');
        }

        $payload = self::DOMAIN."\0".hex2bin($nonce)."\0{$ticketId}\0{$generation}\0{$keyVersion}";
        $secret = hash_hmac('sha256', self::DOMAIN, $this->keyRing->keyFor($keyVersion), true);
        $bytes = hash_hmac('sha256', $payload, $secret, true);

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

    private function audit(Ticket $ticket, ?User $actor, string $action, string $reason, array $metadata = []): void
    {
        $status = $ticket->current_workflow_stage ?: $ticket->status->value;
        $reason = preg_replace('/https?:\/\/\S+|(?<![A-Za-z0-9_-])[A-Za-z0-9_-]{43}(?![A-Za-z0-9_-])|\b[0-9a-f]{64}\b/i', '[redacted]', $reason) ?? '[redacted]';
        $ticket->histories()->create([
            'from_status' => $status,
            'to_status' => $status,
            'action' => $action,
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->role?->key ?? 'public_requester',
            'notes' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
