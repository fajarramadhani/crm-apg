<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['ticket_id', 'public_tracking_token_id', 'public_action_access_token_id', 'action', 'key_hash', 'request_hash', 'result', 'response_status', 'expires_at'])]
class PublicTicketActionIdempotency extends Model
{
    protected function casts(): array
    {
        return ['result' => 'array', 'response_status' => 'integer', 'expires_at' => 'datetime'];
    }
}
