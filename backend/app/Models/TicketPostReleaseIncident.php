<?php

namespace App\Models;

use App\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'deployment_id', 'monitoring_session_id', 'incident_number', 'reported_by', 'assigned_to', 'title', 'description', 'business_impact', 'severity', 'status', 'requires_rollback', 'resolution_notes', 'resolved_by', 'resolved_at'])]
class TicketPostReleaseIncident extends Model
{
    protected function casts(): array
    {
        return [
            'status' => IncidentStatus::class,
            'requires_rollback' => 'boolean',
            'resolved_at' => 'datetime',
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

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
