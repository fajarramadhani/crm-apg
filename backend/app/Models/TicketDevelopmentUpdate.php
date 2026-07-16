<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'created_by', 'progress_percentage', 'summary', 'completed_items', 'remaining_items', 'blockers', 'next_steps', 'is_internal'])]
class TicketDevelopmentUpdate extends Model
{
    protected function casts(): array
    {
        return ['completed_items' => 'array', 'remaining_items' => 'array', 'blockers' => 'array', 'next_steps' => 'array', 'is_internal' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
