<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DynamicAssignmentService
{
    public function assignPrimary(
        Ticket $ticket,
        User $actor,
        User $targetUser,
        ?string $notes = null,
        ?string $reason = null,
        ?DateTimeInterface $targetCompletedAt = null
    ): TicketAssignment {
        return DB::transaction(function () use ($ticket, $actor, $targetUser, $notes, $reason, $targetCompletedAt): TicketAssignment {
            /** @var Ticket $lockedTicket */
            $lockedTicket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            $this->validateCandidateUser($targetUser);

            /** @var TicketAssignment|null $activePrimary */
            $activePrimary = $lockedTicket->assignments()
                ->where('assignment_type', 'primary')
                ->where('is_current', true)
                ->first();

            if ($activePrimary && $activePrimary->assigned_to === $targetUser->id) {
                return $activePrimary;
            }

            $now = Date::now();

            if ($activePrimary) {
                $activePrimary->update([
                    'ended_at' => $now,
                    'is_current' => false,
                ]);
            }

            // End active secondary assignment for target user if it exists on this ticket
            $lockedTicket->assignments()
                ->where('assigned_to', $targetUser->id)
                ->where('assignment_type', 'secondary')
                ->where('is_current', true)
                ->update([
                    'ended_at' => $now,
                    'is_current' => false,
                ]);

            $targetUserRoleKey = $targetUser->role?->key ?? 'supervisor_it';
            $isSupervisor = $targetUserRoleKey === 'supervisor_it';

            /** @var TicketAssignment $newAssignment */
            $newAssignment = $lockedTicket->assignments()->create([
                'assigned_to' => $targetUser->id,
                'assigned_by' => $actor->id,
                'assignment_type' => 'primary',
                'role_at_assignment' => $targetUserRoleKey,
                'acting_as_pic' => $isSupervisor,
                'started_at' => $now,
                'is_current' => true,
                'notes' => $notes,
                'assignment_reason' => $reason,
                'target_completed_at' => $targetCompletedAt,
            ]);

            $lockedTicket->fill([
                'current_assignee_id' => $targetUser->id,
                'assigned_by' => $actor->id,
                'assigned_at' => $lockedTicket->assigned_at ?? $now,
            ])->save();

            $lockedTicket->assignmentHistories()->create([
                'assignment_id' => $newAssignment->id,
                'action' => $activePrimary ? 'reassigned' : 'assigned',
                'actor_id' => $actor->id,
                'from_user_id' => $activePrimary?->assigned_to,
                'to_user_id' => $targetUser->id,
                'assignment_type' => 'primary',
                'notes' => $notes,
                'metadata' => [
                    'reason' => $reason,
                    'role_at_assignment' => $targetUserRoleKey,
                    'acting_as_pic' => $isSupervisor,
                ],
                'created_at' => $now,
            ]);

            return $newAssignment;
        });
    }

    public function addSecondary(
        Ticket $ticket,
        User $actor,
        User $targetUser,
        ?string $notes = null,
        ?string $reason = null
    ): TicketAssignment {
        return DB::transaction(function () use ($ticket, $actor, $targetUser, $notes, $reason): TicketAssignment {
            /** @var Ticket $lockedTicket */
            $lockedTicket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            $this->validateCandidateUser($targetUser);

            $existingActive = $lockedTicket->assignments()
                ->where('assigned_to', $targetUser->id)
                ->where('is_current', true)
                ->exists();

            if ($existingActive) {
                throw ValidationException::withMessages([
                    'user_id' => ['User already has an active assignment on this ticket.'],
                ]);
            }

            $now = Date::now();
            $targetUserRoleKey = $targetUser->role?->key ?? 'pic_it_support';
            $isSupervisor = $targetUserRoleKey === 'supervisor_it';

            /** @var TicketAssignment $newAssignment */
            $newAssignment = $lockedTicket->assignments()->create([
                'assigned_to' => $targetUser->id,
                'assigned_by' => $actor->id,
                'assignment_type' => 'secondary',
                'role_at_assignment' => $targetUserRoleKey,
                'acting_as_pic' => $isSupervisor,
                'started_at' => $now,
                'is_current' => true,
                'notes' => $notes,
                'assignment_reason' => $reason,
            ]);

            $lockedTicket->assignmentHistories()->create([
                'assignment_id' => $newAssignment->id,
                'action' => 'secondary_added',
                'actor_id' => $actor->id,
                'from_user_id' => null,
                'to_user_id' => $targetUser->id,
                'assignment_type' => 'secondary',
                'notes' => $notes,
                'metadata' => [
                    'reason' => $reason,
                    'role_at_assignment' => $targetUserRoleKey,
                    'acting_as_pic' => $isSupervisor,
                ],
                'created_at' => $now,
            ]);

            return $newAssignment;
        });
    }

    public function removeSecondary(
        Ticket $ticket,
        User $actor,
        User $targetUser,
        ?string $notes = null,
        ?string $reason = null
    ): void {
        DB::transaction(function () use ($ticket, $actor, $targetUser, $notes, $reason): void {
            /** @var Ticket $lockedTicket */
            $lockedTicket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            /** @var TicketAssignment|null $secondaryAssignment */
            $secondaryAssignment = $lockedTicket->assignments()
                ->where('assigned_to', $targetUser->id)
                ->where('assignment_type', 'secondary')
                ->where('is_current', true)
                ->first();

            if (! $secondaryAssignment) {
                throw ValidationException::withMessages([
                    'user_id' => ['User is not an active secondary PIC on this ticket.'],
                ]);
            }

            $now = Date::now();
            $secondaryAssignment->update([
                'ended_at' => $now,
                'is_current' => false,
            ]);

            $lockedTicket->assignmentHistories()->create([
                'assignment_id' => $secondaryAssignment->id,
                'action' => 'secondary_removed',
                'actor_id' => $actor->id,
                'from_user_id' => $targetUser->id,
                'to_user_id' => null,
                'assignment_type' => 'secondary',
                'notes' => $notes,
                'metadata' => ['reason' => $reason],
                'created_at' => $now,
            ]);
        });
    }

    public function reassign(
        Ticket $ticket,
        User $actor,
        User $newPrimaryUser,
        ?string $notes = null,
        ?string $reason = null
    ): TicketAssignment {
        return $this->assignPrimary($ticket, $actor, $newPrimaryUser, $notes, $reason);
    }

    public function takeover(
        Ticket $ticket,
        User $supervisor,
        ?string $notes = null,
        ?string $reason = null
    ): TicketAssignment {
        return DB::transaction(function () use ($ticket, $supervisor, $notes, $reason): TicketAssignment {
            /** @var Ticket $lockedTicket */
            $lockedTicket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            $this->validateCandidateUser($supervisor);

            /** @var TicketAssignment|null $activePrimary */
            $activePrimary = $lockedTicket->assignments()
                ->where('assignment_type', 'primary')
                ->where('is_current', true)
                ->first();

            if ($activePrimary && $activePrimary->assigned_to === $supervisor->id) {
                return $activePrimary;
            }

            $now = Date::now();

            if ($activePrimary) {
                $activePrimary->update([
                    'ended_at' => $now,
                    'is_current' => false,
                ]);
            }

            // End active secondary assignment if supervisor was secondary
            $lockedTicket->assignments()
                ->where('assigned_to', $supervisor->id)
                ->where('assignment_type', 'secondary')
                ->where('is_current', true)
                ->update([
                    'ended_at' => $now,
                    'is_current' => false,
                ]);

            /** @var TicketAssignment $newAssignment */
            $newAssignment = $lockedTicket->assignments()->create([
                'assigned_to' => $supervisor->id,
                'assigned_by' => $supervisor->id,
                'assignment_type' => 'primary',
                'role_at_assignment' => 'supervisor_it',
                'acting_as_pic' => true,
                'started_at' => $now,
                'is_current' => true,
                'notes' => $notes,
                'assignment_reason' => $reason,
            ]);

            $lockedTicket->fill([
                'current_assignee_id' => $supervisor->id,
                'assigned_by' => $supervisor->id,
                'assigned_at' => $lockedTicket->assigned_at ?? $now,
            ])->save();

            $lockedTicket->assignmentHistories()->create([
                'assignment_id' => $newAssignment->id,
                'action' => 'taken_over',
                'actor_id' => $supervisor->id,
                'from_user_id' => $activePrimary?->assigned_to,
                'to_user_id' => $supervisor->id,
                'assignment_type' => 'primary',
                'notes' => $notes,
                'metadata' => [
                    'reason' => $reason,
                    'role_at_assignment' => 'supervisor_it',
                    'acting_as_pic' => true,
                ],
                'created_at' => $now,
            ]);

            return $newAssignment;
        });
    }

    private function validateCandidateUser(User $user): void
    {
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'user_id' => ['Inactive users cannot be assigned to tickets.'],
            ]);
        }

        if (! $user->isEligibleTicketAssignee()) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected user is not an eligible PIC candidate.'],
            ]);
        }
    }
}
