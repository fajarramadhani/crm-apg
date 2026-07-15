<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'created_by', 'version', 'lock_version', 'is_current', 'solution_summary', 'implementation_steps', 'affected_components', 'dependencies', 'estimated_effort_minutes', 'risk_level', 'risk_description', 'rollback_plan', 'testing_plan', 'deployment_consideration', 'status', 'submitted_at', 'reviewed_at', 'reviewed_by', 'review_notes'])]
class TicketSolutionPlan extends Model
{
    protected function casts(): array
    {
        return ['is_current' => 'boolean', 'implementation_steps' => 'array', 'affected_components' => 'array', 'dependencies' => 'array', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
