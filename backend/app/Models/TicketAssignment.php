<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'assigned_to', 'assigned_by', 'assignment_type', 'role_at_assignment', 'acting_as_pic', 'started_at', 'ended_at', 'target_completed_at', 'is_current', 'notes', 'assignment_reason'])]
class TicketAssignment extends Model
{
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'target_completed_at' => 'datetime', 'is_current' => 'boolean', 'acting_as_pic' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
