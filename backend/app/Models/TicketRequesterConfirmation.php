<?php

namespace App\Models;

use App\Enums\RequesterConfirmationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'deployment_id', 'requester_id', 'requested_by', 'requested_at', 'responded_at', 'status', 'confirmation_notes', 'rejection_reason', 'version'])]
class TicketRequesterConfirmation extends Model
{
    protected function casts(): array
    {
        return [
            'status' => RequesterConfirmationStatus::class,
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(TicketDeployment::class, 'deployment_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
