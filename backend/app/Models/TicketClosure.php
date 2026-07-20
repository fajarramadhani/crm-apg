<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'deployment_id', 'monitoring_session_id', 'requester_confirmation_id', 'closed_by', 'closed_at', 'closure_summary', 'resolution_summary', 'business_outcome', 'final_sla_result', 'final_status'])]
class TicketClosure extends Model
{
    protected function casts(): array
    {
        return [
            'final_status' => TicketStatus::class,
            'closed_at' => 'datetime',
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

    public function monitoringSession(): BelongsTo
    {
        return $this->belongsTo(TicketMonitoringSession::class, 'monitoring_session_id');
    }

    public function requesterConfirmation(): BelongsTo
    {
        return $this->belongsTo(TicketRequesterConfirmation::class, 'requester_confirmation_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
