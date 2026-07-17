<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TicketUatAssignmentService
{
    public function __construct(private TicketTransitionService $transitions) {}

    public function assign(Ticket $ticket, User $actor, int $requesterId, ?string $notes): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor, $requesterId, $notes): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($locked->status !== TicketStatus::ReadyForUat) {
                throw new InvalidTicketTransition($locked->status->value, 'Ticket must be ready for UAT before assignment.');
            }

            if ($locked->requester_id !== $requesterId) {
                throw new InvalidTicketTransition($locked->status->value, 'UAT assignee must be the ticket requester.');
            }

            $targetUser = User::query()->where('is_active', true)->findOrFail($requesterId);
            if (! $targetUser->hasRole('requester')) {
                throw new InvalidTicketTransition($locked->status->value, 'UAT assignee must have the requester role.');
            }

            $hasActiveAssignment = $locked->uatAssignments()
                ->where('is_current', true)
                ->where('requester_id', $requesterId)
                ->exists();

            if ($hasActiveAssignment) {
                throw new InvalidTicketTransition($locked->status->value, 'This requester is already actively assigned for UAT.');
            }

            $locked->uatAssignments()->where('is_current', true)->update([
                'is_current' => false,
                'ended_at' => now(),
            ]);

            $locked->uatAssignments()->create([
                'requester_id' => $requesterId,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
                'is_current' => true,
                'notes' => $notes,
            ]);

            return $this->transitions->assignUat($locked, $actor, $targetUser, $notes);
        });
    }
}
