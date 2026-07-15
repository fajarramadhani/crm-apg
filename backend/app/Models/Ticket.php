<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['ticket_number', 'requester_id', 'division_id', 'branch_id', 'application_id', 'application_module_id', 'ticket_category_id', 'requested_priority_id', 'title', 'description', 'business_impact', 'urgency', 'incident_occurred_at', 'affected_url', 'expected_result', 'actual_result', 'reproduction_steps', 'request_purpose', 'target_needed_at', 'change_reason', 'expected_impact', 'recurring_indication', 'status', 'current_division_id', 'submitted_at', 'validated_at', 'rejected_at'])]
class Ticket extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['status' => TicketStatus::class, 'incident_occurred_at' => 'datetime', 'target_needed_at' => 'datetime', 'submitted_at' => 'datetime', 'validated_at' => 'datetime', 'rejected_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function currentDivision(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'current_division_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function applicationModule(): BelongsTo
    {
        return $this->belongsTo(ApplicationModule::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function requestedPriority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'requested_priority_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class)->oldest('created_at')->oldest('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->oldest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }
}
