<?php

namespace App\Models;

use App\Enums\DeploymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ticket_id', 'release_plan_id', 'rollback_plan_id', 'cycle_number', 'deployment_number', 'environment', 'release_version', 'scheduled_start_at', 'scheduled_end_at', 'actual_start_at', 'actual_end_at', 'deployment_owner_id', 'release_owner_id', 'approved_by', 'status', 'deployment_summary', 'execution_reference', 'change_reference', 'result_summary', 'failure_reason', 'version'])]
class TicketDeployment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => DeploymentStatus::class,
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function releasePlan(): BelongsTo
    {
        return $this->belongsTo(TicketReleasePlan::class, 'release_plan_id');
    }

    public function rollbackPlan(): BelongsTo
    {
        return $this->belongsTo(TicketRollbackPlan::class, 'rollback_plan_id');
    }

    public function deploymentOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deployment_owner_id');
    }

    public function releaseOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'release_owner_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(TicketDeploymentStep::class, 'deployment_id')->orderBy('step_number');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TicketDeploymentHistory::class, 'deployment_id')->oldest();
    }

    public function monitoringSessions(): HasMany
    {
        return $this->hasMany(TicketMonitoringSession::class, 'deployment_id')->orderBy('cycle_number');
    }
}
