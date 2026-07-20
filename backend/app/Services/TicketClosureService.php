<?php

namespace App\Services;

use App\Enums\RequesterConfirmationStatus;
use App\Enums\TicketStatus;
use App\Events\TicketClosed;
use App\Events\TicketRequesterConfirmed;
use App\Models\Ticket;
use App\Models\TicketClosure;
use App\Models\TicketRequesterConfirmation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TicketClosureService
{
    public function respondToConfirmation(Ticket $ticket, User $actor, array $data): TicketRequesterConfirmation
    {
        if ($ticket->status !== TicketStatus::AwaitingRequesterConfirmation) {
            throw new InvalidArgumentException('Ticket is not awaiting confirmation.');
        }

        return DB::transaction(function () use ($ticket, $actor, $data) {
            $isConfirmed = $data['status'] === RequesterConfirmationStatus::Accepted->value;

            $confirmation = TicketRequesterConfirmation::create([
                'ticket_id' => $ticket->id,
                'deployment_id' => $ticket->current_deployment_id,
                'requester_id' => $ticket->requester_id,
                'requested_by' => $actor->id, // Ideally who requested it, but we use the current user responding here just for simplicity or null. Actually requested_by is in the table. Let's assume the IT Lead requested it or the system. We'll set requested_by to IT Lead or system if possible.
                'requested_at' => $ticket->requester_confirmation_requested_at ?? now(),
                'responded_at' => now(),
                'status' => $data['status'],
                'confirmation_notes' => $data['notes'] ?? null,
                'rejection_reason' => $data['rejection_reason'] ?? null,
            ]);

            $ticket->requester_confirmed_at = now();

            if ($isConfirmed) {
                // If confirmed, it can automatically close or wait for manual closure
                // For simplicity, let's say it stays in awaiting_requester_confirmation (or a new 'ready_to_close' state)
                // until closed, or we auto-close it. Let's auto-close it if confirmed.
                $ticket->status = TicketStatus::Closed;
                $ticket->save();

                $ticket->histories()->create([
                    'from_status' => TicketStatus::AwaitingRequesterConfirmation->value,
                    'to_status' => TicketStatus::Closed->value,
                    'action' => 'requester_confirmed',
                    'actor_id' => $actor->id,
                    'actor_role' => $actor->role_id ?? 'requester',
                    'notes' => 'Requester confirmed the resolution.',
                ]);

                $this->createClosureRecord($ticket, $actor, $confirmation, [
                    'resolution_summary' => 'Requester confirmed the deployment.',
                    'business_outcome' => 'success',
                ]);

                event(new TicketRequesterConfirmed($ticket, $actor));
                event(new TicketClosed($ticket, $actor));
            } else {
                // Rejected. Move back to triage or development. Let's use NeedRevision for now or Triage
                $ticket->status = TicketStatus::Triage;
                $ticket->save();

                $ticket->histories()->create([
                    'from_status' => TicketStatus::AwaitingRequesterConfirmation->value,
                    'to_status' => TicketStatus::Triage->value,
                    'action' => 'requester_rejected',
                    'actor_id' => $actor->id,
                    'actor_role' => $actor->role_id ?? 'requester',
                    'notes' => 'Requester rejected the resolution: '.($data['rejection_reason'] ?? ''),
                ]);
            }

            return $confirmation;
        });
    }

    public function close(Ticket $ticket, User $actor, array $data): TicketClosure
    {
        if (in_array($ticket->status, [TicketStatus::Closed, TicketStatus::Cancelled, TicketStatus::Rejected])) {
            throw new InvalidArgumentException('Ticket is already closed or cancelled.');
        }

        return DB::transaction(function () use ($ticket, $actor, $data) {
            $fromStatus = $ticket->status->value;
            $ticket->status = TicketStatus::Closed;
            $ticket->closed_by = $actor->id;
            $ticket->save();

            $ticket->histories()->create([
                'from_status' => $fromStatus,
                'to_status' => TicketStatus::Closed->value,
                'action' => 'ticket_closed',
                'actor_id' => $actor->id,
                'actor_role' => $actor->role_id ?? 'system',
                'notes' => $data['closure_summary'] ?? 'Ticket manually closed.',
            ]);

            $closure = $this->createClosureRecord($ticket, $actor, null, $data);

            event(new TicketClosed($ticket, $actor));

            return $closure;
        });
    }

    private function createClosureRecord(Ticket $ticket, User $actor, ?TicketRequesterConfirmation $confirmation, array $data): TicketClosure
    {
        return TicketClosure::create([
            'ticket_id' => $ticket->id,
            'deployment_id' => $ticket->current_deployment_id,
            'monitoring_session_id' => $ticket->current_monitoring_session_id,
            'requester_confirmation_id' => $confirmation?->id,
            'closed_by' => $actor->id,
            'closed_at' => now(),
            'closure_summary' => $data['closure_summary'] ?? 'Ticket closed.',
            'resolution_summary' => $data['resolution_summary'] ?? null,
            'business_outcome' => $data['business_outcome'] ?? null,
            'final_sla_result' => $data['final_sla_result'] ?? 'met',
            'final_status' => TicketStatus::Closed,
        ]);
    }
}
