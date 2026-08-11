<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowTransition extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'from_stage_id',
        'to_stage_id',
        'action_key',
        'name',
        'requires_notes',
        'metadata',
    ];

    protected $casts = [
        'requires_notes' => 'boolean',
        'metadata' => 'array',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_id');
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'to_stage_id');
    }

    public function transitionPermissions(): HasMany
    {
        return $this->hasMany(WorkflowTransitionPermission::class, 'transition_id');
    }

    /** Convenience alias used by WorkflowSnapshotBuilder and WorkflowValidatorService. */
    public function permissions(): HasMany
    {
        return $this->transitionPermissions();
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(WorkflowTransitionNotification::class, 'transition_id');
    }
}
