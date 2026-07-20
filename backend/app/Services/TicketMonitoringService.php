<?php

namespace App\Services;

use App\Enums\MonitoringCheckStatus;
use App\Enums\MonitoringStatus;
use App\Enums\TicketStatus;
use App\Events\TicketMonitoringCompleted;
use App\Events\TicketMonitoringStarted;
use App\Models\Ticket;
use App\Models\TicketDeployment;
use App\Models\TicketMonitoringCheck;
use App\Models\TicketMonitoringSession;
use App\Models\TicketPostReleaseIncident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TicketMonitoringService
{
    public function start(Ticket $ticket, TicketDeployment $deployment, User $actor, array $data): TicketMonitoringSession
    {
        if ($ticket->status !== TicketStatus::Deployed) {
            throw new InvalidArgumentException('Ticket must be deployed to start monitoring.');
        }

        return DB::transaction(function () use ($ticket, $deployment, $actor, $data) {
            $count = TicketMonitoringSession::where('deployment_id', $deployment->id)->count();
            $cycleNumber = $count + 1;

            $session = TicketMonitoringSession::create([
                'ticket_id' => $ticket->id,
                'deployment_id' => $deployment->id,
                'cycle_number' => $cycleNumber,
                'started_by' => $actor->id,
                'started_at' => now(),
                'planned_end_at' => $data['planned_end_at'] ?? now()->addDays(3),
                'status' => MonitoringStatus::Active,
            ]);

            $ticket->status = TicketStatus::Monitoring;
            $ticket->save();

            $ticket->histories()->create([
                'from_status' => TicketStatus::Deployed->value,
                'to_status' => TicketStatus::Monitoring->value,
                'action' => 'monitoring_started',
                'actor_id' => $actor->id,
                'actor_role' => $actor->role_id ?? 'system',
                'notes' => 'Post-release monitoring started.',
            ]);

            event(new TicketMonitoringStarted($ticket, $actor));

            return $session;
        });
    }

    public function recordCheck(TicketMonitoringSession $session, array $data, User $actor): TicketMonitoringCheck
    {
        if ($session->status !== MonitoringStatus::Active) {
            throw new InvalidArgumentException('Monitoring session is not in progress.');
        }

        return DB::transaction(function () use ($session, $data, $actor) {
            $count = $session->checks()->count();

            $check = $session->checks()->create([
                'check_number' => $count + 1,
                'category' => $data['category'] ?? 'general',
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'expected_condition' => $data['expected_condition'] ?? null,
                'actual_result' => $data['actual_result'],
                'status' => $data['status'] ?? MonitoringCheckStatus::Passed,
                'checked_by' => $actor->id,
                'checked_at' => now(),
                'notes' => $data['notes'] ?? null,
                'evidence_attachment_id' => $data['evidence_attachment_id'] ?? null,
            ]);

            $session->ticket->touch();

            return $check;
        });
    }

    public function recordIncident(TicketMonitoringSession $session, array $data, User $actor): TicketPostReleaseIncident
    {
        if ($session->status !== MonitoringStatus::Active) {
            throw new InvalidArgumentException('Monitoring session is not in progress.');
        }

        return DB::transaction(function () use ($session, $data, $actor) {
            $count = $session->incidents()->count();
            $incidentNumber = 'INC-'.$session->deployment->deployment_number.'-'.str_pad($count + 1, 2, '0', STR_PAD_LEFT);

            $incident = $session->incidents()->create([
                'ticket_id' => $session->ticket_id,
                'deployment_id' => $session->deployment_id,
                'incident_number' => $incidentNumber,
                'reported_by' => $actor->id,
                'assigned_to' => $data['assigned_to'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'],
                'business_impact' => $data['business_impact'] ?? null,
                'severity' => $data['severity'] ?? 'low',
                'status' => $data['status'] ?? 'open',
                'requires_rollback' => $data['requires_rollback'] ?? false,
            ]);

            $session->ticket->touch();

            return $incident;
        });
    }

    public function complete(TicketMonitoringSession $session, User $actor, array $data): TicketMonitoringSession
    {
        if ($session->status !== MonitoringStatus::Active) {
            throw new InvalidArgumentException('Only in progress monitoring sessions can be completed.');
        }

        return DB::transaction(function () use ($session, $actor, $data) {
            $ticket = $session->ticket;

            $session->status = MonitoringStatus::Completed;
            $session->completed_at = now();
            $session->overall_result = $data['overall_result'] ?? 'success';
            $session->summary = $data['summary'] ?? null;
            $session->save();

            $ticket->status = TicketStatus::AwaitingRequesterConfirmation;
            $ticket->monitoring_completed_at = now();
            $ticket->requester_confirmation_requested_at = now();
            $ticket->save();

            $ticket->histories()->create([
                'from_status' => TicketStatus::Monitoring->value,
                'to_status' => TicketStatus::AwaitingRequesterConfirmation->value,
                'action' => 'monitoring_completed',
                'actor_id' => $actor->id,
                'actor_role' => $actor->role_id ?? 'system',
                'notes' => 'Monitoring completed. Result: '.$session->overall_result,
            ]);

            event(new TicketMonitoringCompleted($ticket, $actor));

            return $session;
        });
    }
}
