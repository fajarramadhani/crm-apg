<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'stage_key',
        'name',
        'description',
        'order',
        'stage_type',
        'is_initial',
        'is_terminal',
        'metadata',
    ];

    protected $casts = [
        'is_initial' => 'boolean',
        'is_terminal' => 'boolean',
        'metadata' => 'array',
        'order' => 'integer',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_id');
    }

    public function outgoingTransitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'from_stage_id');
    }

    public function incomingTransitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'to_stage_id');
    }

    public function stageFields(): HasMany
    {
        return $this->hasMany(WorkflowStageField::class, 'stage_id');
    }

    /** Convenience alias used by WorkflowSnapshotBuilder. */
    public function fields(): HasMany
    {
        return $this->stageFields();
    }
}
