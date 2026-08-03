<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['token_hash', 'branch_id', 'identity_type', 'identity_hash', 'identity_ciphertext', 'expires_at', 'last_used_at', 'revoked_at'])]
class PublicRequestHistoryAccessToken extends Model
{
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'last_used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
