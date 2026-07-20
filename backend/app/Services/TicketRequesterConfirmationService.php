<?php

namespace App\Services;

use App\Enums\RequesterConfirmationStatus;
use App\Enums\TicketStatus;
use App\Events\TicketRequesterConfirmed;
use App\Models\Ticket;
use App\Models\TicketRequesterConfirmation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TicketRequesterConfirmationService
{
    public function respondToConfirmation(Ticket $ticket, User $actor, array $data): TicketRequesterConfirmation
    {
        if ($ticket->status !== TicketStatus::AwaitingRequesterConfirmation) {
            throw new InvalidArgumentException('Ticket is not awaiting confirmation.');
        }

        // Prevent duplicate responses
        $existing = TicketRequesterConfirmation::where('ticket_id', $ticket->id)
            ->whereNotNull('responded_at')
            ->latest()
            ->first();

        if ($existing) {
            throw new ConflictHttpException('Confirmation has already been responded to.');
        }

        $isConfirmed = $data['status'] === RequesterConfirmationStatus::Accepted->value;

        if (! $isConfirmed && empty($data['rejection_reason'])) {
            throw new InvalidArgumentException('A rejection reason is required when rejecting a confirmation.');
        }

        return DB::transaction(function () use ($ticket, $actor, $data, $isConfirmed) {
            $confirmation = TicketRequesterConfirmation::create([
                'ticket_id' => $ticket->id,
                'deployment_id' => $ticket->current_deployment_id,
                'requester_id' => $ticket->requester_id,
                'requested_by' => $ticket->release_owner_id ?? 1, // Fallback
                'requested_at' => $ticket->requester_confirmation_requested_at ?? now(),
                'responded_at' => now(),
                'status' => $data['status'],
                'confirmation_notes' => $data['notes'] ?? null,
                'rejection_reason' => $data['rejection_reason'] ?? null,
            ]);

            $ticket->requester_confirmed_at = now();

            if ($isConfirmed) {
                // Ticket status remains awaiting_requester_confirmation, but it is now accepted
                // IT Lead will manually close it later.
                $ticket->save();

                $ticket->histories()->create([
                    'from_status' => TicketStatus::AwaitingRequesterConfirmation->value,
                    'to_status' => TicketStatus::AwaitingRequesterConfirmation->value,
                    'action' => 'requester_confirmed',
                    'actor_id' => $actor->id,
                    'actor_role' => $actor->role_id ?? 'requester',
                    'notes' => 'Requester accepted the deployment. Ticket is ready for closure.',
                ]);

                event(new TicketRequesterConfirmed($ticket, $actor));
            } else {
                // Rejected. Move to reopened then development_in_progress
                $ticket->status = TicketStatus::Reopened;
                $ticket->save();

                $ticket->histories()->create([
                    'from_status' => TicketStatus::AwaitingRequesterConfirmation->value,
                    'to_status' => TicketStatus::Reopened->value,
                    'action' => 'requester_rejected',
                    'actor_id' => $actor->id,
                    'actor_role' => $actor->role_id ?? 'requester',
                    'notes' => 'Requester rejected the resolution: '.($data['rejection_reason'] ?? ''),
                ]);

                $ticket->status = TicketStatus::DevelopmentInProgress;
                $ticket->save();

                $ticket->histories()->create([
                    'from_status' => TicketStatus::Reopened->value,
                    'to_status' => TicketStatus::DevelopmentInProgress->value,
                    'action' => 'development_started',
                    'actor_id' => $actor->id,
                    'actor_role' => $actor->role_id ?? 'system',
                    'notes' => 'Ticket moved back to development after requester rejection.',
                ]);
            }

            return $confirmation;
        });
    }
}
