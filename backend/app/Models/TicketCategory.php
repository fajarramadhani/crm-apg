<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code', 'name', 'type', 'description', 'is_active', 'default_workflow_id', 'metadata'])]
class TicketCategory extends Model
{
    use HasActiveScope;

    public const TYPES = ['incident', 'request', 'change', 'problem'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'metadata' => 'array'];
    }

    public function defaultWorkflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'default_workflow_id');
    }
}
