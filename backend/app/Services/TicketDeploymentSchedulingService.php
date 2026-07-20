<?php

namespace App\Services;

use App\Enums\DeploymentStatus;
use App\Enums\TicketStatus;
use App\Events\TicketDeploymentScheduled;
use App\Models\Ticket;
use App\Models\TicketDeployment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class TicketDeploymentSchedulingService
{
    public function schedule(Ticket $ticket, array $data, User $actor): TicketDeployment
    {
        if ($ticket->status !== TicketStatus::ReleaseReady) {
            throw new InvalidArgumentException("Ticket must be in release_ready status. Current status: {$ticket->status->value}");
        }

        $releasePlan = $ticket->releasePlans()->latest('version')->first();
        if (! $releasePlan) {
            throw new RuntimeException('Cannot schedule deployment without a release plan.');
        }

        $rollbackPlan = $ticket->rollbackPlans()->latest('version')->first();

        return DB::transaction(function () use ($ticket, $data, $actor, $releasePlan, $rollbackPlan) {
            $ticketNumberForDeployment = $ticket->ticket_number;
            $count = TicketDeployment::where('ticket_id', $ticket->id)->count();
            $deploymentNumber = $ticketNumberForDeployment.'-DEP-'.str_pad($count + 1, 2, '0', STR_PAD_LEFT);

            $deployment = TicketDeployment::create([
                'ticket_id' => $ticket->id,
                'release_plan_id' => $releasePlan->id,
                'rollback_plan_id' => $rollbackPlan?->id ?? $releasePlan->rollbackPlan?->id ?? 1, // Fallback to avoid constraint error in edge cases
                'cycle_number' => $ticket->deployment_cycle_number + 1,
                'deployment_number' => $deploymentNumber,
                'title' => $data['title'] ?? 'Deployment for '.$ticket->ticket_number,
                'description' => $data['description'] ?? null,
                'environment' => $data['environment'] ?? 'production',
                'release_version' => $data['release_version'] ?? '1.0.0',
                'scheduled_start_at' => $data['scheduled_start_at'],
                'scheduled_end_at' => $data['scheduled_end_at'] ?? null,
                'deployment_owner_id' => $actor->id,
                'release_owner_id' => $ticket->release_owner_id ?? $actor->id,
                'approved_by' => $actor->id,
                'deployment_summary' => $data['description'] ?? 'Scheduled deployment',
                'status' => DeploymentStatus::Scheduled,
                'scheduled_by' => $actor->id,
            ]);

            $ticket->deployment_cycle_number += 1;
            $ticket->status = TicketStatus::DeploymentScheduled;
            $ticket->current_deployment_id = $deployment->id;
            $ticket->save();

            $ticket->histories()->create([
                'from_status' => TicketStatus::ReleaseReady->value,
                'to_status' => TicketStatus::DeploymentScheduled->value,
                'action' => 'deployment_scheduled',
                'actor_id' => $actor->id,
                'actor_role' => $actor->role_id ?? 'system',
                'notes' => $data['notes'] ?? 'Deployment scheduled for '.$deployment->scheduled_start_at,
            ]);

            $deployment->histories()->create([
                'ticket_id' => $ticket->id,
                'action' => 'scheduled',
                'actor_id' => $actor->id,
                'from_status' => null,
                'to_status' => DeploymentStatus::Scheduled->value,
                'notes' => 'Deployment initially scheduled.',
            ]);

            event(new TicketDeploymentScheduled($ticket, $actor));

            return $deployment;
        });
    }
}
