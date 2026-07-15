<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Events\TicketSolutionPlanApproved;
use App\Events\TicketSolutionPlanRevisionRequested;
use App\Events\TicketSolutionPlanSubmitted;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Ticket;
use App\Models\TicketSolutionPlan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class TicketSolutionPlanService
{
    public function __construct(private TicketTransitionService $transitions) {}

    public function create(Ticket $ticket, User $actor, array $data): TicketSolutionPlan
    {
        return DB::transaction(function () use ($ticket, $actor, $data): TicketSolutionPlan {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertOwner($locked, $actor);
            if ($locked->status !== TicketStatus::SolutionPlanning || ! $locked->current_analysis_id || ! $locked->currentAnalysis?->completed_at) {
                throw new InvalidTicketTransition($locked->status->value, 'A completed analysis is required before solution planning.');
            }
            $current = $locked->currentSolutionPlan;
            if ($current && $current->status !== 'revision_requested') {
                throw new InvalidTicketTransition($locked->status->value, 'A current plan already exists.');
            }
            $version = ($locked->solutionPlans()->max('version') ?? 0) + 1;
            $locked->solutionPlans()->update(['is_current' => false]);
            $plan = $locked->solutionPlans()->create([...$this->fields($data), 'created_by' => $actor->id, 'version' => $version, 'is_current' => true, 'status' => 'draft']);
            $locked->update(['current_solution_plan_id' => $plan->id]);
            $this->history($locked, $actor, 'solution_plan_created', ['plan_version' => $version, 'risk_level' => $plan->risk_level, 'estimated_effort_minutes' => $plan->estimated_effort_minutes]);

            return $plan->fresh(['creator', 'reviewer']);
        });
    }

    public function update(Ticket $ticket, TicketSolutionPlan $plan, User $actor, array $data): TicketSolutionPlan
    {
        return DB::transaction(function () use ($ticket, $plan, $actor, $data): TicketSolutionPlan {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $draft = TicketSolutionPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $this->assertOwner($locked, $actor);
            if ($draft->ticket_id !== $locked->id || $locked->current_solution_plan_id !== $draft->id || $locked->status !== TicketStatus::SolutionPlanning || $draft->status !== 'draft') {
                throw new InvalidTicketTransition($locked->status->value, 'Solution plan is no longer editable.');
            }
            $this->assertFresh($draft, $data['expected_lock_version'] ?? null, $locked);
            $draft->update([...$this->fields($data), 'lock_version' => $draft->lock_version + 1]);
            $this->history($locked, $actor, 'solution_plan_updated', ['plan_version' => $draft->version, 'risk_level' => $draft->risk_level, 'estimated_effort_minutes' => $draft->estimated_effort_minutes]);

            return $draft->fresh(['creator', 'reviewer']);
        });
    }

    public function submit(Ticket $ticket, TicketSolutionPlan $plan, User $actor): TicketSolutionPlan
    {
        $submitted = DB::transaction(function () use ($ticket, $plan, $actor): TicketSolutionPlan {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $draft = TicketSolutionPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $this->assertOwner($locked, $actor);
            if ($draft->ticket_id !== $locked->id || $locked->current_solution_plan_id !== $draft->id || $draft->status !== 'draft') {
                throw new InvalidTicketTransition($locked->status->value, 'Solution plan is no longer submittable.');
            }
            $draft->update(['status' => 'submitted', 'submitted_at' => now(), 'reviewed_at' => null, 'reviewed_by' => null]);
            $action = $draft->version > 1 ? 'solution_plan_resubmitted' : 'solution_plan_submitted';
            $this->transitions->phaseTransition($locked, $actor, TicketStatus::SolutionPlanning, TicketStatus::PlanReview, $action, metadata: ['plan_version' => $draft->version, 'risk_level' => $draft->risk_level, 'estimated_effort_minutes' => $draft->estimated_effort_minutes], mutate: fn (Ticket $item) => $item->plan_submitted_at = now());

            return $draft->fresh(['ticket', 'creator', 'reviewer']);
        });
        TicketSolutionPlanSubmitted::dispatch($submitted->ticket, $actor, $submitted);

        return $submitted;
    }

    public function approve(Ticket $ticket, TicketSolutionPlan $plan, User $actor): TicketSolutionPlan
    {
        $approved = DB::transaction(function () use ($ticket, $plan, $actor): TicketSolutionPlan {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $submitted = TicketSolutionPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $this->assertReviewer($actor);
            if ($submitted->ticket_id !== $locked->id || $locked->current_solution_plan_id !== $submitted->id || $submitted->status !== 'submitted') {
                throw new InvalidTicketTransition($locked->status->value, 'Solution plan has already been reviewed.');
            }
            $submitted->update(['status' => 'approved', 'reviewed_at' => now(), 'reviewed_by' => $actor->id, 'review_notes' => null]);
            $this->transitions->phaseTransition($locked, $actor, TicketStatus::PlanReview, TicketStatus::ReadyForDevelopment, 'solution_plan_approved', metadata: ['plan_version' => $submitted->version, 'reviewer' => $actor->id, 'status_plan' => 'approved'], mutate: fn (Ticket $item) => $item->plan_approved_at = now());

            return $submitted->fresh(['ticket', 'creator', 'reviewer']);
        });
        TicketSolutionPlanApproved::dispatch($approved->ticket, $actor, $approved);

        return $approved;
    }

    public function requestRevision(Ticket $ticket, TicketSolutionPlan $plan, User $actor, string $notes): TicketSolutionPlan
    {
        $rejected = DB::transaction(function () use ($ticket, $plan, $actor, $notes): TicketSolutionPlan {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $submitted = TicketSolutionPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $this->assertReviewer($actor);
            if ($submitted->ticket_id !== $locked->id || $locked->current_solution_plan_id !== $submitted->id || $submitted->status !== 'submitted') {
                throw new InvalidTicketTransition($locked->status->value, 'Solution plan has already been reviewed.');
            }
            $submitted->update(['status' => 'revision_requested', 'reviewed_at' => now(), 'reviewed_by' => $actor->id, 'review_notes' => $notes]);
            $this->transitions->phaseTransition($locked, $actor, TicketStatus::PlanReview, TicketStatus::SolutionPlanning, 'solution_plan_revision_requested', $notes, ['plan_version' => $submitted->version, 'reviewer' => $actor->id, 'status_plan' => 'revision_requested']);

            return $submitted->fresh(['ticket', 'creator', 'reviewer']);
        });
        TicketSolutionPlanRevisionRequested::dispatch($rejected->ticket, $actor, $rejected);

        return $rejected;
    }

    private function fields(array $data): array
    {
        return collect($data)->only(['solution_summary', 'implementation_steps', 'affected_components', 'dependencies', 'estimated_effort_minutes', 'risk_level', 'risk_description', 'rollback_plan', 'testing_plan', 'deployment_consideration'])->all();
    }

    private function assertOwner(Ticket $ticket, User $actor): void
    {
        if ($ticket->current_assignee_id !== $actor->id || ! $ticket->assignments()->where('assigned_to', $actor->id)->where('is_current', true)->exists()) {
            throw new AuthorizationException;
        }
    }

    private function assertReviewer(User $actor): void
    {
        if (! $actor->hasPermission('ticket.solution_plan.approve')) {
            throw new AuthorizationException;
        }
    }

    private function assertFresh(TicketSolutionPlan $plan, ?int $expected, Ticket $ticket): void
    {
        if (! $expected || $plan->lock_version !== $expected) {
            throw new InvalidTicketTransition($ticket->status->value, 'The solution plan has changed; reload before saving.');
        }
    }

    private function history(Ticket $ticket, User $actor, string $action, array $metadata): void
    {
        $ticket->histories()->create(['from_status' => $ticket->status->value, 'to_status' => $ticket->status->value, 'action' => $action, 'actor_id' => $actor->id, 'actor_role' => $actor->role?->key ?? 'pic', 'metadata' => $metadata]);
    }
}
