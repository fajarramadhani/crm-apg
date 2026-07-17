<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uat_run_id', 'uat_scenario_id', 'executed_by', 'status', 'actual_result', 'notes', 'executed_at',
])]
class TicketUatResult extends Model
{
    protected function casts(): array
    {
        return [
            'executed_at' => 'datetime',
        ];
    }

    public function uatRun(): BelongsTo
    {
        return $this->belongsTo(TicketUatRun::class, 'uat_run_id');
    }

    public function uatScenario(): BelongsTo
    {
        return $this->belongsTo(TicketUatScenario::class, 'uat_scenario_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}
