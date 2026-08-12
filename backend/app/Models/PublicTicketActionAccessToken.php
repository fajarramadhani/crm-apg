<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['token_hash', 'ticket_id', 'public_tracking_token_id', 'branch_id', 'identity_hash', 'action', 'status_hash', 'expires_at', 'last_used_at', 'consumed_at', 'revoked_at'])]
class PublicTicketActionAccessToken extends Model
{
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'last_used_at' => 'datetime', 'consumed_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
