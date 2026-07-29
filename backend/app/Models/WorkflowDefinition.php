<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowDefinition extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'description',
        'version',
        'is_active',
        'config_status',
        'published_at',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'published_at' => 'datetime',
    ];

    // ---------------------------------------------------------------------------
    // Lifecycle helpers
    // ---------------------------------------------------------------------------

    public function isDraft(): bool
    {
        return $this->config_status === 'draft';
    }

    public function isPublished(): bool
    {
        return in_array($this->config_status, ['published', 'active', 'inactive'], true);
    }

    public function isActive(): bool
    {
        return $this->config_status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->config_status === 'inactive';
    }

    // ---------------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------------

    public function scopeDraft($query)
    {
        return $query->where('config_status', 'draft');
    }

    public function scopePublished($query)
    {
        return $query->whereIn('config_status', ['published', 'active', 'inactive']);
    }

    public function scopeActive($query)
    {
        return $query->where('config_status', 'active');
    }

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowStage::class, 'workflow_id')->orderBy('order');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'workflow_id');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(WorkflowCondition::class, 'workflow_id');
    }

    public function approvalConfigs(): HasMany
    {
        return $this->hasMany(WorkflowApprovalConfig::class, 'workflow_id');
    }
}
