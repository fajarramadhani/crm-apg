<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['priority_id', 'response_minutes', 'resolution_minutes', 'working_calendar_id', 'is_active'])]
class SlaPolicy extends Model
{
    use HasActiveScope;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'response_minutes' => 'integer', 'resolution_minutes' => 'integer'];
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'priority_id');
    }

    public function workingCalendar(): BelongsTo
    {
        return $this->belongsTo(WorkingCalendar::class);
    }
}
