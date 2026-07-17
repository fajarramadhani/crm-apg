<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uat_finding_id', 'from_status', 'to_status', 'action', 'actor_id', 'notes', 'metadata', 'created_at',
])]
class TicketUatFindingHistory extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function uatFinding(): BelongsTo
    {
        return $this->belongsTo(TicketUatFinding::class, 'uat_finding_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
