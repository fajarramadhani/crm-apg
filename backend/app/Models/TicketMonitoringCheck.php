<?php

namespace App\Models;

use App\Enums\MonitoringCheckStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['monitoring_session_id', 'check_number', 'category', 'title', 'description', 'expected_condition', 'actual_result', 'status', 'checked_by', 'checked_at', 'notes', 'evidence_attachment_id', 'version'])]
class TicketMonitoringCheck extends Model
{
    protected function casts(): array
    {
        return [
            'status' => MonitoringCheckStatus::class,
            'checked_at' => 'datetime',
        ];
    }

    public function monitoringSession(): BelongsTo
    {
        return $this->belongsTo(TicketMonitoringSession::class, 'monitoring_session_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(TicketAttachment::class, 'evidence_attachment_id');
    }
}
