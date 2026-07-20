<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TicketReleasePlan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'affected_components' => 'array',
            'dependencies' => 'array',
            'data_migration_required' => 'boolean',
            'downtime_required' => 'boolean',
            'proposed_start_at' => 'datetime',
            'validation_steps' => 'array',
            'monitoring_plan' => 'array',
            'pre_deployment_steps' => 'array',
            'deployment_steps' => 'array',
            'database_execution_steps' => 'array',
            'post_deployment_steps' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(TicketApprovalRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function releaseOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'release_owner_id');
    }

    public function rollbackPlan(): HasOne
    {
        return $this->hasOne(TicketRollbackPlan::class, 'release_plan_id')->latestOfMany('version');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TicketReleaseChecklistItem::class, 'release_plan_id');
    }
}
