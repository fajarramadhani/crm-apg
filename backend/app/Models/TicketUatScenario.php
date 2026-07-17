<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ticket_id', 'created_by', 'scenario_number', 'title', 'business_objective',
    'preconditions', 'steps', 'expected_result', 'acceptance_criteria', 'priority', 'is_active',
])]
class TicketUatScenario extends Model
{
    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'acceptance_criteria' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(TicketUatResult::class, 'uat_scenario_id');
    }
}
