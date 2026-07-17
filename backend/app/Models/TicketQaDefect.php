<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ticket_id', 'qa_test_run_id', 'qa_test_case_id', 'reported_by', 'assigned_to',
    'defect_number', 'title', 'description', 'severity', 'priority', 'steps_to_reproduce',
    'expected_result', 'actual_result', 'environment', 'status', 'resolved_by',
    'resolved_at', 'resolution_notes', 'verified_by', 'verified_at',
])]
class TicketQaDefect extends Model
{
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function testRun(): BelongsTo
    {
        return $this->belongsTo(TicketQaTestRun::class, 'qa_test_run_id');
    }

    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TicketQaTestCase::class, 'qa_test_case_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TicketQaDefectHistory::class, 'defect_id')->oldest('created_at')->oldest('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class, 'defect_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class, 'defect_id')->oldest();
    }
}
