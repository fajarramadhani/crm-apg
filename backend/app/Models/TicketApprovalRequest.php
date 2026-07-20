<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketApprovalRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'completed_at' => 'datetime', 'proposed_release_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(TicketApprovalStep::class, 'approval_request_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TicketApprovalActionHistory::class, 'approval_request_id');
    }

    public function releasePlans(): HasMany
    {
        return $this->hasMany(TicketReleasePlan::class);
    }
}
