<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_id', 'user_id', 'type', 'comment', 'is_internal', 'defect_id', 'uat_finding_id'])]
class TicketComment extends Model
{
    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defect(): BelongsTo
    {
        return $this->belongsTo(TicketQaDefect::class, 'defect_id');
    }

    public function uatFinding(): BelongsTo
    {
        return $this->belongsTo(TicketUatFinding::class, 'uat_finding_id');
    }
}
