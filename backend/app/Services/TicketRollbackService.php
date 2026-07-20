<?php

namespace App\Services;

use App\Enums\RollbackStatus;
use App\Enums\TicketStatus;
use App\Events\TicketRollbackCompleted;
use App\Events\TicketRollbackStarted;
use App\Models\Ticket;
use App\Models\TicketDeployment;
use App\Models\TicketRollbackExecution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TicketRollbackService
{
    public function start(Ticket $ticket, TicketDeployment $deployment, User $actor, array $data): TicketRollbackExecution
    {
        if ($ticket->status !== TicketStatus::DeploymentFailed) {
            throw new InvalidArgumentException('Ticket must be in deployment_failed status to rollback.');
        }

        $rollbackPlan = $deployment->rollbackPlan;

        return DB::transaction(function () use ($ticket, $deployment, $rollbackPlan, $actor, $data) {
            $count = TicketRollbackExecution::where('deployment_id', $deployment->id)->count();
            $rollbackNumber = $deployment->deployment_number.'-RB-'.str_pad($count + 1, 2, '0', STR_PAD_LEFT);

            $rollback = TicketRollbackExecution::create([
                'ticket_id' => $ticket->id,
                'deployment_id' => $deployment->id,
                'rollback_plan_id' => $rollbackPlan?->id,
                'rollback_number' => $rollbackNumber,
                'requested_by' => $data['requested_by'] ?? $actor->id,
                'approved_by' => $data['approved_by'] ?? null,
                'executed_by' => $actor->id,
                'reason' => $data['reason'],
                'trigger_source' => $data['trigger_source'] ?? 'manual',
                'started_at' => now(),
                'status' => RollbackStatus::InProgress,
            ]);

            $ticket->status = TicketStatus::RollbackInProgress;
            $ticket->save();

            $ticket->histories()->create([
                'from_status' => TicketStatus::DeploymentFailed->value,
                'to_status' => TicketStatus::RollbackInProgress->value,
                'action' => 'rollback_started',
                'actor_id' => $actor->id,
                'actor_role' => $actor->role_id ?? 'system',
                'notes' => 'Rollback started: '.$data['reason'],
            ]);

            event(new TicketRollbackStarted($ticket, $actor));

            return $rollback;
        });
    }

    public function complete(TicketRollbackExecution $rollback, User $actor, string $summary): TicketRollbackExecution
    {
        if ($rollback->status !== RollbackStatus::InProgress) {
            throw new InvalidArgumentException('Only in progress rollbacks can be completed.');
        }

        return DB::transaction(function () use ($rollback, $actor, $summary) {
            $ticket = $rollback->ticket;

            $rollback->status = RollbackStatus::Completed;
            $rollback->completed_at = now();
            $rollback->result_summary = $summary;
            $rollback->save();

            $ticket->status = TicketStatus::RolledBack;
            $ticket->save();

            $ticket->histories()->create([
                'from_status' => TicketStatus::RollbackInProgress->value,
                'to_status' => TicketStatus::RolledBack->value,
                'action' => 'rollback_completed',
                'actor_id' => $actor->id,
                'actor_role' => $actor->role_id ?? 'system',
                'notes' => 'Rollback completed: '.$summary,
            ]);

            event(new TicketRollbackCompleted($ticket, $actor));

            return $rollback;
        });
    }
}
