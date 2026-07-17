<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ticket_id', 'qa_user_id', 'cycle_number', 'run_number', 'environment', 'build_reference', 'started_at', 'completed_at', 'status', 'summary'])]
class TicketQaTestRun extends Model
{
    protected function casts(): array
    {
        return [
            'cycle_number' => 'integer',
            'run_number' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function qaUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qa_user_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(TicketQaTestResult::class, 'qa_test_run_id');
    }

    public function defects(): HasMany
    {
        return $this->hasMany(TicketQaDefect::class, 'qa_test_run_id');
    }
}
