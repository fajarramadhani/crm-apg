<?php

namespace App\Models;

use App\Enums\MonitoringStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ticket_id', 'deployment_id', 'cycle_number', 'started_by', 'started_at', 'planned_end_at', 'completed_at', 'status', 'overall_result', 'summary', 'version'])]
class TicketMonitoringSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => MonitoringStatus::class,
            'started_at' => 'datetime',
            'planned_end_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(TicketMonitoringCheck::class, 'monitoring_session_id')->orderBy('check_number');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(TicketPostReleaseIncident::class, 'monitoring_session_id')->orderBy('incident_number');
    }
}
