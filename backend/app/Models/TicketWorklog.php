<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'user_id', 'work_date', 'minutes_spent', 'activity_type', 'description', 'progress_before', 'progress_after', 'is_internal'])]
class TicketWorklog extends Model
{
    protected function casts(): array
    {
        return ['work_date' => 'date', 'is_internal' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
