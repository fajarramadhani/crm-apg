<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'analyst_id', 'version', 'lock_version', 'is_current', 'problem_summary', 'root_cause', 'technical_impact', 'business_impact', 'affected_components', 'evidence', 'assumptions', 'limitations', 'analysis_started_at', 'completed_at'])]
class TicketAnalysis extends Model
{
    protected function casts(): array
    {
        return ['is_current' => 'boolean', 'affected_components' => 'array', 'analysis_started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function analyst(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analyst_id');
    }
}
