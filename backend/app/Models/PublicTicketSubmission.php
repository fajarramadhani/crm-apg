<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['key_hash', 'request_hash', 'ticket_id', 'tracking_token_id', 'expires_at'])]
class PublicTicketSubmission extends Model
{
    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function trackingToken(): BelongsTo
    {
        return $this->belongsTo(PublicTicketTrackingToken::class, 'tracking_token_id');
    }
}
