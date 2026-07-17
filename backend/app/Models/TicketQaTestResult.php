<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['qa_test_run_id', 'qa_test_case_id', 'executed_by', 'status', 'actual_result', 'notes', 'executed_at'])]
class TicketQaTestResult extends Model
{
    protected function casts(): array
    {
        return [
            'executed_at' => 'datetime',
        ];
    }

    public function testRun(): BelongsTo
    {
        return $this->belongsTo(TicketQaTestRun::class, 'qa_test_run_id');
    }

    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TicketQaTestCase::class, 'qa_test_case_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}
