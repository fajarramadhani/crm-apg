<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketReleaseChecklistItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'completed_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function releasePlan(): BelongsTo
    {
        return $this->belongsTo(TicketReleasePlan::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReleaseChecklistTemplate::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TicketReleaseChecklistHistory::class, 'checklist_item_id');
    }
}
