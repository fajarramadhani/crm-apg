<?php

namespace App\Services;

use App\Enums\DeploymentStatus;
use App\Enums\DeploymentStepStatus;
use App\Enums\TicketStatus;
use App\Events\TicketDeploymentCompleted;
use App\Events\TicketDeploymentFailed;
use App\Events\TicketDeploymentStarted;
use App\Models\TicketDeployment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TicketDeploymentExecutionService
{
    public function start(TicketDeployment $deployment, User $actor, ?string $notes = null): TicketDeployment
    {
        if ($deployment->status !== DeploymentStatus::Scheduled) {
            throw new InvalidArgumentException('Deployment must be scheduled to start.');
        }

        return DB::transaction(function () use ($deployment, $actor, $notes) {
            $ticket = $deployment->ticket;

            $deployment->status = DeploymentStatus::InProgress;
            $deployment->actual_start_at = now();
            $deployment->save();

            $deployment->histories()->create([
                'ticket_id' => $ticket->id,
                'action' => 'started',
                'actor_id' => $actor->id,
                'from_status' => DeploymentStatus::Scheduled->value,
                'to_status' => DeploymentStatus::InProgress->value,
                'notes' => $notes ?? 'Deployment started.',
            ]);

            $ticket->status = TicketStatus::DeploymentInProgress;
            $ticket->save();

            $ticket->histories()->create([
                'from_status' => TicketStatus::DeploymentScheduled->value,
                'to_status' => TicketStatus::DeploymentInProgress->value,
                'action' => 'deployment_started',
                'actor_id' => $actor->id,
                'actor_role' => $actor->role_id ?? 'system',
                'notes' => $notes ?? 'Deployment in progress.',
            ]);

            event(new TicketDeploymentStarted($ticket, $actor));

            return $deployment;
        });
    }

    public function manageStep(TicketDeployment $deployment, array $data, User $actor): void
    {
        if ($deployment->status !== DeploymentStatus::InProgress) {
            throw new InvalidArgumentException('Steps can only be managed when deployment is in progress.');
        }

        DB::transaction(function () use ($deployment, $data, $actor) {
            $step = $deployment->steps()->updateOrCreate(
                ['step_number' => $data['step_number']],
                [
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'step_type' => $data['step_type'] ?? 'manual',
                    'is_required' => $data['is_required'] ?? true,
                    'status' => $data['status'] ?? DeploymentStepStatus::Pending,
                    'executed_by' => $actor->id,
                    'started_at' => $data['started_at'] ?? null,
                    'completed_at' => ($data['status'] ?? DeploymentStepStatus::Pending) === DeploymentStepStatus::Completed ? now() : null,
                    'notes' => $data['notes'] ?? null,
                    'evidence_attachment_id' => $data['evidence_attachment_id'] ?? null,
                ]
            );

            // Ensure ticket updated_at is bumped
            $deployment->ticket->touch();
        });
    }

    public function complete(TicketDeployment $deployment, User $actor, ?string $summary = null): TicketDeployment
    {
        if ($deployment->status !== DeploymentStatus::InProgress) {
            throw new InvalidArgumentException('Only in progress deployments can be completed.');
        }

        // Validate required steps are complete (or not, depending on strictness - we'll allow overriding for now if PIC explicitly completes)

        return DB::transaction(function () use ($deployment, $actor, $summary) {
            $ticket = $deployment->ticket;

            $deployment->status = DeploymentStatus::Succeeded;
            $deployment->actual_end_at = now();
            $deployment->result_summary = $summary;
            $deployment->save();

            $deployment->histories()->create([
                'ticket_id' => $ticket->id,
                'action' => 'completed',
                'actor_id' => $actor->id,
                'from_status' => DeploymentStatus::InProgress->value,
                'to_status' => DeploymentStatus::Succeeded->value,
                'notes' => 'Deployment marked as completed.',
            ]);

            $ticket->status = TicketStatus::Deployed;
            $ticket->latest_deployment_result = DeploymentStatus::Succeeded->value;
            $ticket->deployment_cycle_number = $ticket->deployment_cycle_number + 1;
            $ticket->save();

            $ticket->histories()->create([
                'from_status' => TicketStatus::DeploymentInProgress->value,
                'to_status' => TicketStatus::Deployed->value,
                'action' => 'deployment_completed',
                'actor_id' => $actor->id,
                'actor_role' => $actor->role_id ?? 'system',
                'notes' => $summary ?? 'Deployment completed successfully.',
            ]);

            event(new TicketDeploymentCompleted($ticket, $actor));

            return $deployment;
        });
    }

    public function fail(TicketDeployment $deployment, User $actor, string $reason): TicketDeployment
    {
        if ($deployment->status !== DeploymentStatus::InProgress) {
            throw new InvalidArgumentException('Only in progress deployments can fail.');
        }

        return DB::transaction(function () use ($deployment, $actor, $reason) {
            $ticket = $deployment->ticket;

            $deployment->status = DeploymentStatus::Failed;
            $deployment->actual_end_at = now();
            $deployment->result_summary = $reason;
            $deployment->save();

            $deployment->histories()->create([
                'ticket_id' => $ticket->id,
                'action' => 'failed',
                'actor_id' => $actor->id,
                'from_status' => DeploymentStatus::InProgress->value,
                'to_status' => DeploymentStatus::Failed->value,
                'notes' => 'Deployment failed: '.$reason,
            ]);

            $ticket->status = TicketStatus::DeploymentFailed;
            $ticket->latest_deployment_result = DeploymentStatus::Failed->value;
            $ticket->deployment_cycle_number = $ticket->deployment_cycle_number + 1;
            $ticket->save();

            $ticket->histories()->create([
                'from_status' => TicketStatus::DeploymentInProgress->value,
                'to_status' => TicketStatus::DeploymentFailed->value,
                'action' => 'deployment_failed',
                'actor_id' => $actor->id,
                'actor_role' => $actor->role_id ?? 'system',
                'notes' => 'Deployment failed: '.$reason,
            ]);

            event(new TicketDeploymentFailed($ticket, $actor));

            return $deployment;
        });
    }
}
