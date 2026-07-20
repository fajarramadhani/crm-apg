<?php

namespace App\Services;

use App\Enums\RequesterConfirmationStatus;
use App\Enums\TicketStatus;
use App\Events\TicketClosed;
use App\Models\Ticket;
use App\Models\TicketClosure;
use App\Models\TicketRequesterConfirmation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TicketClosureService
{
    public function close(Ticket $ticket, User $actor, array $data): TicketClosure
    {
        if (in_array($ticket->status, [TicketStatus::Closed, TicketStatus::Cancelled, TicketStatus::Rejected])) {
            throw new InvalidArgumentException('Ticket is already closed or cancelled.');
        }

        if ($ticket->status !== TicketStatus::AwaitingRequesterConfirmation) {
            throw new InvalidArgumentException('Ticket must be awaiting requester confirmation to be closed.');
        }

        $confirmation = TicketRequesterConfirmation::where('ticket_id', $ticket->id)
            ->latest('responded_at')
            ->first();

        if (! $confirmation || $confirmation->status !== RequesterConfirmationStatus::Accepted) {
            throw new InvalidArgumentException('Ticket cannot be closed before requester confirmation is accepted.');
        }

        if ($ticket->postReleaseIncidents()->whereNotIn('status', [\App\Enums\IncidentStatus::Closed, \App\Enums\IncidentStatus::Resolved])->exists()) {
            throw new InvalidArgumentException('Cannot close ticket with open incidents.');
        }

        if ($ticket->rollbackExecutions()->whereNotIn('status', [\App\Enums\RollbackStatus::Succeeded, \App\Enums\RollbackStatus::Failed, \App\Enums\RollbackStatus::Cancelled])->exists()) {
            throw new InvalidArgumentException('Cannot close ticket with an active rollback.');
        }

        if ($ticket->qaDefects()->where('status', '!=', 'closed')->exists()) {
            throw new InvalidArgumentException('Cannot close ticket with open QA defects.');
        }

        if ($ticket->uatFindings()->where('status', '!=', 'closed')->exists()) {
            throw new InvalidArgumentException('Cannot close ticket with open UAT findings.');
        }

        if ($ticket->deployments()->where('status', \App\Enums\DeploymentStatus::InProgress)->exists()) {
            throw new InvalidArgumentException('Cannot close ticket with an active deployment run.');
        }

        if ($ticket->monitoringSessions()->where('status', \App\Enums\MonitoringStatus::Active)->exists()) {
            throw new InvalidArgumentException('Cannot close ticket with an active monitoring run.');
        }

        if ($ticket->internalTestRuns()->where('status', 'in_progress')->exists()) {
            throw new InvalidArgumentException('Cannot close ticket with an active internal test run.');
        }

        if ($ticket->qaTestRuns()->where('status', 'in_progress')->exists()) {
            throw new InvalidArgumentException('Cannot close ticket with an active QA test run.');
        }

        if ($ticket->uatRuns()->where('status', 'in_progress')->exists()) {
            throw new InvalidArgumentException('Cannot close ticket with an active UAT run.');
        }

        return DB::transaction(function () use ($ticket, $actor, $data, $confirmation) {
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

            $closure = $this->createClosureRecord($ticket, $actor, $confirmation, $data);

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
