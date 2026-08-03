<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['challenge_token_hash', 'branch_id', 'identity_type', 'identity_hash', 'identity_ciphertext', 'otp_hash', 'expires_at', 'attempts_remaining', 'resend_available_at', 'sent_at', 'consumed_at', 'superseded_at'])]
class PublicRequestHistoryChallenge extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime', 'attempts_remaining' => 'integer',
            'resend_available_at' => 'datetime', 'sent_at' => 'datetime',
            'consumed_at' => 'datetime', 'superseded_at' => 'datetime',
        ];
    }
}
