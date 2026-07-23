<?php

namespace App\Services;

use App\Enums\DeploymentStatus;
use App\Enums\DeploymentStepStatus;
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
        $allowedStatuses = [TicketStatus::ReleaseReady, TicketStatus::DeploymentScheduled, TicketStatus::DeploymentInProgress];
        if (! in_array($ticket->status, $allowedStatuses, true)) {
            throw new InvalidArgumentException("Ticket status cannot be scheduled. Current status: {$ticket->status->value}");
        }

        $existing = $ticket->deployments()->latest('version')->first();
        if ($existing) {
            $existing->update([
                'scheduled_start_at' => $data['scheduled_start_at'],
                'scheduled_end_at' => $data['scheduled_end_at'] ?? $existing->scheduled_end_at,
            ]);

            return $existing;
        }

        $releasePlan = $ticket->releasePlans()->latest('version')->first();
        if (! $releasePlan) {
            throw new RuntimeException('Cannot schedule deployment without a release plan.');
        }

        $rollbackPlan = $ticket->rollbackPlans()->latest('version')->first();

        return DB::transaction(function () use ($ticket, $data, $actor, $releasePlan, $rollbackPlan) {
            $lockedTicket = Ticket::where('id', $ticket->id)->lockForUpdate()->first();

            $ticketNumberForDeployment = $lockedTicket->ticket_number;
            $count = TicketDeployment::where('ticket_id', $lockedTicket->id)->count();
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

            $lockedTicket->deployment_cycle_number += 1;
            $lockedTicket->status = TicketStatus::DeploymentScheduled;
            $lockedTicket->current_deployment_id = $deployment->id;
            $lockedTicket->save();

            $lockedTicket->histories()->create([
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

            $this->createDeploymentSteps($deployment, $releasePlan, $actor);

            event(new TicketDeploymentScheduled($ticket, $actor));

            return $deployment;
        });
    }

    private function createDeploymentSteps(TicketDeployment $deployment, $releasePlan, User $actor): void
    {
        $stepNumber = 1;

        $sections = [
            'pre_deployment_steps' => 'pre_deployment',
            'deployment_steps' => 'deployment',
            'database_execution_steps' => 'database',
            'validation_steps' => 'validation',
            'post_deployment_steps' => 'post_deployment',
        ];

        $hasSteps = false;

        foreach ($sections as $field => $stepType) {
            $steps = $releasePlan->{$field} ?? [];
            if (! is_array($steps)) {
                continue;
            }

            foreach ($steps as $stepData) {
                $hasSteps = true;
                $title = is_array($stepData) ? ($stepData['title'] ?? 'Step '.$stepNumber) : (string) $stepData;
                $description = is_array($stepData) ? ($stepData['description'] ?? $stepData['title'] ?? $title) : (string) $stepData;
                $deployment->steps()->create([
                    'step_number' => $stepNumber++,
                    'title' => $title,
                    'description' => $description,
                    'step_type' => $stepType,
                    'is_required' => is_array($stepData) ? ($stepData['is_required'] ?? true) : true,
                    'status' => DeploymentStepStatus::Pending,
                    'executed_by' => null,
                ]);
            }
        }

        if (! $hasSteps) {
            $deployment->steps()->create([
                'step_number' => 1,
                'title' => 'Default Deployment Step',
                'description' => 'Automatically generated step because no steps were found in release plan.',
                'step_type' => 'deployment',
                'is_required' => true,
                'status' => DeploymentStepStatus::Pending,
            ]);
        }
    }
}
