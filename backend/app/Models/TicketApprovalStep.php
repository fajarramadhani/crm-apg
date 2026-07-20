<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketApprovalStep extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'acted_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(TicketApprovalRequest::class, 'approval_request_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
