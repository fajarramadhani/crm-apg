<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['working_calendar_id', 'date', 'name', 'is_recurring'])]
class Holiday extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'is_recurring' => 'boolean'];
    }

    public function workingCalendar(): BelongsTo
    {
        return $this->belongsTo(WorkingCalendar::class);
    }
}
