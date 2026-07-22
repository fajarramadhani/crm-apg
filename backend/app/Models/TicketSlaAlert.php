<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketSlaAlert extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
        'triggered_at' => 'datetime',
        'resolved_at' => 'datetime',
        'threshold_percent' => 'integer',
        'elapsed_minutes' => 'integer',
        'target_minutes' => 'integer',
        'remaining_minutes' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function ticketSla(): BelongsTo
    {
        return $this->belongsTo(TicketSla::class);
    }
}
