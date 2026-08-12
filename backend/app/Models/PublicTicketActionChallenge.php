<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['derivation_nonce', 'challenge_token_hash', 'ticket_id', 'public_tracking_token_id', 'branch_id', 'identity_hash', 'identity_ciphertext', 'action', 'status_hash', 'otp_hash', 'expires_at', 'attempts_remaining', 'resend_available_at', 'sent_at', 'consumed_at', 'superseded_at'])]
class PublicTicketActionChallenge extends Model
{
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'resend_available_at' => 'datetime', 'sent_at' => 'datetime', 'consumed_at' => 'datetime', 'superseded_at' => 'datetime'];
    }
}
