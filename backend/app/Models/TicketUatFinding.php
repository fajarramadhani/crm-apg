<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ticket_id', 'uat_run_id', 'uat_scenario_id', 'reported_by', 'assigned_to',
    'finding_number', 'title', 'description', 'business_impact', 'severity', 'status',
    'resolution_notes', 'resolved_by', 'resolved_at', 'verified_by', 'verified_at',
])]
class TicketUatFinding extends Model
{
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function uatRun(): BelongsTo
    {
        return $this->belongsTo(TicketUatRun::class, 'uat_run_id');
    }

    public function uatScenario(): BelongsTo
    {
        return $this->belongsTo(TicketUatScenario::class, 'uat_scenario_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TicketUatFindingHistory::class, 'uat_finding_id')->oldest('created_at')->oldest('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class, 'uat_finding_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class, 'uat_finding_id')->oldest();
    }
}
