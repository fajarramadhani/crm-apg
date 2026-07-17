<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ticket_id', 'created_by', 'case_number', 'title', 'test_type', 'preconditions', 'steps', 'expected_result', 'priority', 'is_regression', 'is_active'])]
class TicketQaTestCase extends Model
{
    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'is_regression' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(TicketQaTestResult::class, 'qa_test_case_id');
    }
}
