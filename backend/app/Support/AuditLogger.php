<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class AuditLogger
{
    /**
     * Record an immutable audit event with optional actor, subject, and context.
     * Safe to call from console context: HTTP-specific fields stay nullable.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $metadata
     */
    public static function record(
        string $event,
        ?User $actor = null,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
    ): AuditLog {
        $request = request();

        return AuditLog::query()->create([
            'event' => $event,
            'actor_id' => $actor?->getKey(),
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'request_id' => $request->attributes->get('request_id'),
            'ip_address' => $request->ip(),
            'user_agent' => self::shortUserAgent($request->userAgent()),
            'old_values' => $oldValues === [] ? null : $oldValues,
            'new_values' => $newValues === [] ? null : $newValues,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    private static function shortUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        return substr($userAgent, 0, 500);
    }
}
