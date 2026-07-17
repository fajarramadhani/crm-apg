<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ticket_id', 'requester_id', 'cycle_number', 'run_number', 'environment',
    'build_reference', 'started_at', 'completed_at', 'status', 'summary',
])]
class TicketUatRun extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(TicketUatResult::class, 'uat_run_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(TicketUatFinding::class, 'uat_run_id');
    }
}
