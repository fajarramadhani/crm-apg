<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowApprovalConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'stage_id',
        'approval_type',
        'label',
        'notes_required',
        'is_active',
    ];

    protected $casts = [
        'notes_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'stage_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowApprovalStep::class, 'approval_config_id');
    }
}
