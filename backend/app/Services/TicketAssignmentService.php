<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Events\TicketAssigned;
use App\Exceptions\InvalidTicketTransition;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TicketAssignmentService
{
    public function __construct(private SlaDeadlineService $deadlines) {}

    public function assign(Ticket $ticket, User $actor, int $priorityId, int $picId, ?string $notes): Ticket
    {
        [$fresh, $pic] = DB::transaction(function () use ($ticket, $actor, $priorityId, $picId, $notes): array {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->status !== TicketStatus::Triage || $locked->assignments()->where('assignment_type', 'primary')->where('is_current', true)->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'Ticket is no longer available for assignment.');
            }
            $priority = TicketPriority::query()->whereKey($priorityId)->where('is_active', true)->first();
            if (! $priority) {
                throw ValidationException::withMessages(['final_priority_id' => ['The selected final priority is inactive or invalid.']]);
            }
            $pic = User::query()->with('role')->whereKey($picId)->where('is_active', true)->first();
            if (! $pic || ! $pic->hasRole('pic')) {
                throw ValidationException::withMessages(['pic_user_id' => ['The selected user must be an active PIC.']]);
            }
            $policy = SlaPolicy::query()->with('workingCalendar')->where('priority_id', $priority->id)->where('is_active', true)->whereHas('workingCalendar', fn ($q) => $q->where('is_active', true))->first();
            if (! $policy) {
                throw ValidationException::withMessages(['final_priority_id' => ['No active SLA policy and working calendar exists for this priority.']]);
            }
            $startedAt = Date::now()->toImmutable();
            $due = $this->deadlines->calculate($policy, $startedAt);
            $locked->assignments()->create(['assigned_to' => $pic->id, 'assigned_by' => $actor->id, 'assignment_type' => 'primary', 'started_at' => $startedAt, 'is_current' => true, 'notes' => $notes]);
            $locked->fill(['final_priority_id' => $priority->id, 'sla_policy_id' => $policy->id, 'working_calendar_id' => $policy->working_calendar_id, 'response_due_at' => $due['response_due_at'], 'resolution_due_at' => $due['resolution_due_at'], 'sla_timezone' => $due['timezone'], 'current_assignee_id' => $pic->id, 'assigned_by' => $actor->id, 'assigned_at' => $startedAt, 'status' => TicketStatus::Assigned])->save();
            $locked->histories()->create(['from_status' => TicketStatus::Triage->value, 'to_status' => TicketStatus::Assigned->value, 'action' => 'assigned', 'actor_id' => $actor->id, 'actor_role' => $actor->role?->key ?? 'it_lead', 'notes' => $notes, 'metadata' => ['final_priority_id' => $priority->id, 'sla_policy_id' => $policy->id, 'working_calendar_id' => $policy->working_calendar_id, 'pic_user_id' => $pic->id, 'response_due_at' => $due['response_due_at']?->toISOString(), 'resolution_due_at' => $due['resolution_due_at']->toISOString(), 'timezone' => $due['timezone']]]);

            return [$locked->fresh(), $pic];
        });
        TicketAssigned::dispatch($fresh, $actor, $pic);

        return $fresh;
    }
}
