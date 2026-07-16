<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['test_run_id', 'test_case_id', 'executed_by', 'status', 'actual_result', 'notes', 'executed_at'])]
class TicketInternalTestResult extends Model
{
    protected function casts(): array
    {
        return ['executed_at' => 'datetime'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(TicketInternalTestRun::class, 'test_run_id');
    }

    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TicketInternalTestCase::class, 'test_case_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}
