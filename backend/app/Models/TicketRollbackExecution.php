<?php

namespace App\Models;

use App\Enums\RollbackStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'deployment_id', 'rollback_plan_id', 'rollback_number', 'requested_by', 'approved_by', 'executed_by', 'reason', 'trigger_source', 'started_at', 'completed_at', 'status', 'result_summary', 'failure_reason', 'version'])]
class TicketRollbackExecution extends Model
{
    protected function casts(): array
    {
        return [
            'status' => RollbackStatus::class,
            'started_at' => 'datetime',
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

    public function rollbackPlan(): BelongsTo
    {
        return $this->belongsTo(TicketRollbackPlan::class, 'rollback_plan_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}
