<?php

namespace App\Models;

use App\Enums\DeploymentStepStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['deployment_id', 'step_number', 'title', 'description', 'step_type', 'is_required', 'status', 'executed_by', 'started_at', 'completed_at', 'notes', 'evidence_attachment_id', 'version'])]
class TicketDeploymentStep extends Model
{
    protected function casts(): array
    {
        return [
            'status' => DeploymentStepStatus::class,
            'is_required' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(TicketDeployment::class, 'deployment_id');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(TicketAttachment::class, 'evidence_attachment_id');
    }
}
