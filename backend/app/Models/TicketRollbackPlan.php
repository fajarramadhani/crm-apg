<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketRollbackPlan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rollback_steps' => 'array', 'data_recovery_steps' => 'array', 'validation_after_rollback' => 'array'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function releasePlan(): BelongsTo
    {
        return $this->belongsTo(TicketReleasePlan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
