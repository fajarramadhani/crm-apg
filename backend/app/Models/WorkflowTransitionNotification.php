<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransitionNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'transition_id',
        'recipient_type',
        'channel',
        'template_code',
    ];

    public function transition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTransition::class, 'transition_id');
    }
}
