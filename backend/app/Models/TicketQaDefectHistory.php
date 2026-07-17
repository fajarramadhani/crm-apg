<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['defect_id', 'from_status', 'to_status', 'action', 'actor_id', 'actor_role', 'notes', 'metadata'])]
class TicketQaDefectHistory extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function defect(): BelongsTo
    {
        return $this->belongsTo(TicketQaDefect::class, 'defect_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
