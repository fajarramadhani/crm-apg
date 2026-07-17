<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TicketQaAssignmentService
{
    public function __construct(private TicketTransitionService $transitions) {}

    public function assign(Ticket $ticket, User $actor, int $qaUserId, ?string $notes): Ticket
    {
        $qaUser = User::query()->with('role')->whereKey($qaUserId)->where('is_active', true)->first();
        if (! $qaUser || ! $qaUser->hasRole('qa')) {
            throw ValidationException::withMessages(['qa_user_id' => ['The selected user must be an active QA.']]);
        }

        return DB::transaction(function () use ($ticket, $actor, $qaUser, $notes): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($locked->status !== TicketStatus::ReadyForQa) {
                throw new InvalidTicketTransition($locked->status->value, 'QA can only be assigned to tickets in ready_for_qa status.');
            }

            // Verify if there is already an active QA assignment or assignee
            if ($locked->qa_assignee_id !== null || $locked->qaAssignments()->where('is_current', true)->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'An active QA assignment already exists.');
            }

            // Perform transition and save QA assignee details
            $locked = $this->transitions->assignQa($locked, $actor, $qaUser, $notes);

            $locked->qaAssignments()->create([
                'qa_user_id' => $qaUser->id,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
                'is_current' => true,
                'notes' => $notes,
            ]);

            return $locked->fresh(['qaAssignee', 'qaAssignments']);
        });
    }
}
