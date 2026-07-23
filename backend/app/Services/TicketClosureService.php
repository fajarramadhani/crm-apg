<?php

namespace App\Services;

use App\Enums\DeploymentStatus;
use App\Enums\IncidentStatus;
use App\Enums\MonitoringStatus;
use App\Enums\RequesterConfirmationStatus;
use App\Enums\RollbackStatus;
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

        $confirmation = TicketRequesterConfirmation::where('ticket_id', $ticket->id)
            ->latest()
            ->first();

        if (! $confirmation || $confirmation->status !== RequesterConfirmationStatus::Accepted) {
            $deploymentId = $ticket->current_deployment_id ?? $ticket->deployments()->latest('version')->first()?->id ?? 1;
            $confirmation = TicketRequesterConfirmation::updateOrCreate(
                ['ticket_id' => $ticket->id],
                [
                    'deployment_id' => $deploymentId,
                    'requester_id' => $ticket->requester_id,
                    'requested_by' => $actor->id,
                    'requested_at' => now(),
                    'responded_at' => now(),
                    'status' => RequesterConfirmationStatus::Accepted,
                    'notes' => 'Confirmed automatically upon IT Lead closure.',
                ]
            );
        }

        if ($ticket->postReleaseIncidents()->whereNotIn('status', [IncidentStatus::Closed, IncidentStatus::Resolved])->exists()) {
            throw new InvalidArgumentException('Cannot close ticket with open incidents.');
        }

        if ($ticket->rollbackExecutions()->whereNotIn('status', [RollbackStatus::Succeeded, RollbackStatus::Failed, RollbackStatus::Cancelled])->exists()) {
            throw new InvalidArgumentException('Cannot close ticket with an active rollback.');
        }

        if ($ticket->qaDefects()->where('status', '!=', 'closed')->exists()) {
            throw new InvalidArgumentException('Cannot close ticket with open QA defects.');
        }

        if ($ticket->uatFindings()->where('status', '!=', 'closed')->exists()) {
            throw new InvalidArgumentException('Cannot close ticket with open UAT findings.');
        }

        if ($ticket->deployments()->where('status', DeploymentStatus::InProgress)->exists()) {
            throw new InvalidArgumentException('Cannot close ticket with an active deployment run.');
        }

        if ($ticket->monitoringSessions()->where('status', MonitoringStatus::Active)->exists()) {
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
            'resolution_summary' => $data['resolution_summary'] ?? $data['closure_summary'] ?? 'Resolved successfully.',
            'business_outcome' => $data['business_outcome'] ?? $data['closure_summary'] ?? 'Resolved successfully.',
            'final_sla_result' => $data['final_sla_result'] ?? 'met',
            'final_status' => TicketStatus::Closed,
        ]);
    }
}
