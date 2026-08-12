<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'derivation_nonce', 'generation', 'key_version', 'token_hash', 'expires_at', 'last_used_at', 'revoked_at', 'created_by', 'revoked_by'])]
class PublicTicketTrackingToken extends Model
{
    protected function casts(): array
    {
        return [
            'generation' => 'integer',
            'key_version' => 'integer',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
