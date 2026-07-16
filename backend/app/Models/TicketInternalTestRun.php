<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ticket_id', 'executed_by', 'run_number', 'started_at', 'completed_at', 'status', 'environment', 'build_reference', 'summary'])]
class TicketInternalTestRun extends Model
{
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(TicketInternalTestResult::class, 'test_run_id');
    }
}
