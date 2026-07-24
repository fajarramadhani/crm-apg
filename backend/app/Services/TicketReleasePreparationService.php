<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Events\TicketReleasePlanApproved;
use App\Events\TicketRollbackPlanApproved;
use App\Exceptions\InvalidTicketTransition;
use App\Models\ReleaseChecklistTemplate;
use App\Models\Ticket;
use App\Models\TicketReleaseChecklistItem;
use App\Models\TicketReleasePlan;
use App\Models\TicketRollbackPlan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class TicketReleasePreparationService
{
    public function __construct(private TicketTransitionService $transitions) {}

    public function createPlan(Ticket $ticket, User $actor, array $data): TicketReleasePlan
    {
        return DB::transaction(function () use ($ticket, $actor, $data): TicketReleasePlan {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertEditor($locked, $actor);
            $request = $locked->approvalRequests()->where('status', 'approved')->latest('cycle_number')->firstOrFail();
            $owner = User::query()->whereKey($data['release_owner_id'])->where('is_active', true)->whereHas('role', fn ($q) => $q->whereIn('key', ['it_lead', 'pic']))->first();
            if (! $owner) {
                throw new InvalidTicketTransition($locked->status->value, 'Release owner must be an active IT Lead or PIC.');
            }
            $version = ((int) $locked->releasePlans()->max('version')) + 1;
            $plan = $locked->releasePlans()->create([...$data, 'approval_request_id' => $request->id, 'version' => $version, 'created_by' => $actor->id, 'release_owner_id' => $owner->id, 'status' => 'draft', 'lock_version' => 1]);
            $locked->release_owner_id = $owner->id;
            $locked->release_risk_level = $request->release_risk_level;
            $locked->save();
            $this->ensureChecklist($plan);
            $this->history($locked, $actor, 'release_plan_created', ['version' => $version]);

            return $plan->fresh(['releaseOwner', 'checklistItems']);
        });
    }

    public function updatePlan(TicketReleasePlan $plan, Ticket $ticket, User $actor, array $data, int $lockVersion): TicketReleasePlan
    {
        return DB::transaction(function () use ($plan, $ticket, $actor, $data, $lockVersion): TicketReleasePlan {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertEditor($locked, $actor);
            $current = TicketReleasePlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($current->ticket_id !== $locked->id) {
                abort(404, 'Release plan does not belong to this ticket.');
            }
            if ($current->lock_version !== $lockVersion || $current->status !== 'draft') {
                throw new InvalidTicketTransition($locked->status->value, 'Release plan is stale or not editable.');
            }
            $current->update([...$data, 'lock_version' => $current->lock_version + 1]);

            return $current->fresh(['releaseOwner', 'checklistItems']);
        });
    }

    public function submitPlan(TicketReleasePlan $plan, Ticket $ticket, User $actor): TicketReleasePlan
    {
        return DB::transaction(function () use ($plan, $ticket, $actor): TicketReleasePlan {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertEditor($locked, $actor);
            $current = TicketReleasePlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($current->ticket_id !== $locked->id || ! in_array($current->status, ['draft', 'revision_required'], true)) {
                throw new InvalidTicketTransition($locked->status->value, 'Release plan is not submittable.');
            }
            $current->update(['status' => 'submitted']);
            $this->history($locked, $actor, 'release_plan_submitted', ['version' => $current->version]);

            return $current->fresh(['releaseOwner', 'checklistItems']);
        });
    }

    public function reviewPlan(TicketReleasePlan $plan, Ticket $ticket, User $actor, string $status): TicketReleasePlan
    {
        return DB::transaction(function () use ($plan, $ticket, $actor, $status): TicketReleasePlan {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if (! $actor->hasPermission('ticket.release_plan.review')) {
                throw new AuthorizationException;
            }
            $current = TicketReleasePlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($current->ticket_id !== $locked->id || ! in_array($current->status, ['submitted', 'revision_required'], true)) {
                throw new InvalidTicketTransition($locked->status->value, 'Release plan is not reviewable.');
            }
            $current->update(['status' => $status]);
            if ($status === 'approved') {
                TicketReleasePlanApproved::dispatch($locked, $actor);
            }

            return $current->fresh(['releaseOwner', 'checklistItems']);
        });
    }

    public function createRollback(Ticket $ticket, User $actor, array $data): TicketRollbackPlan
    {
        return DB::transaction(function () use ($ticket, $actor, $data): TicketRollbackPlan {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertEditor($locked, $actor);
            $plan = $locked->releasePlans()->latest('version')->firstOrFail();
            if ($data['data_recovery_steps'] === null && $plan->data_migration_required) {
                throw new InvalidTicketTransition($locked->status->value, 'Database changes require data recovery steps.');
            }
            $version = ((int) $locked->rollbackPlans()->max('version')) + 1;

            return $locked->rollbackPlans()->create([...$data, 'release_plan_id' => $plan->id, 'version' => $version, 'created_by' => $actor->id, 'status' => 'draft', 'lock_version' => 1]);
        });
    }

    public function submitRollback(TicketRollbackPlan $plan, Ticket $ticket, User $actor): TicketRollbackPlan
    {
        return DB::transaction(function () use ($plan, $ticket, $actor): TicketRollbackPlan {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertEditor($locked, $actor);
            $current = TicketRollbackPlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($current->ticket_id !== $locked->id || ! in_array($current->status, ['draft', 'revision_required'], true)) {
                throw new InvalidTicketTransition($locked->status->value, 'Rollback plan is not submittable.');
            }
            $current->update(['status' => 'submitted']);
            $this->history($locked, $actor, 'rollback_plan_submitted', ['version' => $current->version]);

            return $current->fresh(['responsibleUser']);
        });
    }

    public function reviewRollback(TicketRollbackPlan $plan, Ticket $ticket, User $actor, string $status): TicketRollbackPlan
    {
        if (! $actor->hasPermission('ticket.rollback_plan.review')) {
            throw new AuthorizationException;
        }
        abort_unless($plan->ticket_id === $ticket->id, 404);
        if (! in_array($plan->status, ['submitted', 'revision_required'], true)) {
            throw new InvalidTicketTransition($ticket->status->value, 'Rollback plan is not reviewable.');
        }
        $plan->update(['status' => $status]);
        if ($status === 'approved') {
            TicketRollbackPlanApproved::dispatch($ticket, $actor);
        }

        return $plan->fresh(['responsibleUser']);
    }

    public function checklist(Ticket $ticket, User $actor): array
    {
        $this->assertViewer($ticket, $actor);
        $plan = $ticket->releasePlans()->latest('version')->first();

        return $plan ? $plan->checklistItems()->with('completedBy')->orderBy('id')->get()->all() : [];
    }

    public function updateChecklist(TicketReleaseChecklistItem $item, Ticket $ticket, User $actor, string $status, ?string $notes, int $version): TicketReleaseChecklistItem
    {
        return DB::transaction(function () use ($item, $ticket, $actor, $status, $notes, $version): TicketReleaseChecklistItem {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertEditor($locked, $actor);
            $current = TicketReleaseChecklistItem::query()->lockForUpdate()->findOrFail($item->id);
            if ($current->ticket_id !== $locked->id) {
                abort(404);
            }
            if ($current->version !== $version) {
                throw new InvalidTicketTransition($locked->status->value, 'Checklist item is stale.');
            }
            if ($status === 'not_applicable' && $current->is_required && blank($notes)) {
                throw new InvalidTicketTransition($locked->status->value, 'Required checklist needs a reason before not applicable.');
            }
            $from = $current->status;
            $current->update(['status' => $status, 'completed_by' => $status === 'completed' ? $actor->id : null, 'completed_at' => $status === 'completed' ? now() : null, 'notes' => $notes, 'version' => $current->version + 1]);
            $current->histories()->create(['action' => $status === 'completed' ? 'completed' : ($status === 'blocked' ? 'blocked' : 'reopened'), 'actor_id' => $actor->id, 'from_status' => $from, 'to_status' => $status, 'notes' => $notes, 'created_at' => now()]);

            return $current->fresh(['completedBy']);
        });
    }

    public function confirmReady(Ticket $ticket, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if (! $actor->hasPermission('ticket.release_readiness.confirm')) {
                throw new AuthorizationException;
            }
            if ($locked->status !== TicketStatus::ReleasePreparation) {
                throw new InvalidTicketTransition($locked->status->value);
            }
            $request = $locked->approvalRequests()->where('status', 'approved')->latest('cycle_number')->first();
            $plan = $locked->releasePlans()->latest('version')->first();
            $rollback = $locked->rollbackPlans()->latest('version')->first();

            if ($plan && ! $plan->proposed_start_at) {
                $plan->proposed_start_at = now()->addDay();
                $plan->save();
            }

            if (! $request || ! $plan || ! $rollback || $locked->latest_uat_result !== 'accepted' || ! $locked->uat_approved_at || ! $locked->release_owner_id || count($plan->validation_steps ?? []) < 1 || count($plan->monitoring_plan ?? []) < 1) {
                throw new InvalidTicketTransition($locked->status->value, 'Release preparation is incomplete.');
            }
            if ($locked->qaDefects()->whereIn('status', ['open', 'in_progress', 'reopened'])->exists() || $locked->uatFindings()->whereIn('status', ['open', 'in_progress', 'reopened'])->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'Open QA or UAT findings block release readiness.');
            }
            if ($locked->internalTestRuns()->where('status', 'in_progress')->exists() || $locked->qaTestRuns()->where('status', 'in_progress')->exists() || $locked->uatRuns()->where('status', 'in_progress')->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'Active test runs block release readiness.');
            }
            if ($locked->releaseChecklistItems()->where('status', 'blocked')->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'Blocked checklist items prevent release readiness.');
            }

            $plan->update(['status' => 'approved']);
            $rollback->update(['status' => 'approved']);
            $locked->releaseChecklistItems()->where('status', 'pending')->update([
                'status' => 'completed',
                'completed_by' => $actor->id,
                'completed_at' => now(),
            ]);

            return $this->transitions->markReleaseReady($locked, $actor, ['checklist_completed' => $locked->releaseChecklistItems()->where('status', 'completed')->count()]);
        });
    }

    private function ensureChecklist(TicketReleasePlan $plan): void
    {
        if ($plan->checklistItems()->exists()) {
            return;
        }
        $templates = ReleaseChecklistTemplate::query()->where('is_active', true)->orderBy('sort_order')->get();
        foreach ($templates as $template) {
            $plan->checklistItems()->create(['ticket_id' => $plan->ticket_id, 'template_id' => $template->id, 'label' => $template->label, 'description' => $template->description, 'category' => $template->category, 'is_required' => $template->is_required, 'status' => 'pending', 'version' => 1]);
        }
    }

    private function assertEditor(Ticket $ticket, User $actor): void
    {
        if ($ticket->status !== TicketStatus::ReleasePreparation) {
            throw new InvalidTicketTransition($ticket->status->value);
        }
        if ($actor->hasRole('it_lead')) {
            return;
        }
        if (! $actor->hasRole('pic') || $ticket->current_assignee_id !== $actor->id || ! $ticket->assignments()->where('assigned_to', $actor->id)->where('is_current', true)->exists()) {
            throw new AuthorizationException;
        }
    }

    private function assertViewer(Ticket $ticket, User $actor): void
    {
        if ($actor->hasPermission('ticket.release_plan.view')) {
            return;
        }
        throw new AuthorizationException;
    }

    private function history(Ticket $ticket, User $actor, string $action, array $metadata): void
    {
        $ticket->histories()->create(['from_status' => $ticket->status->value, 'to_status' => $ticket->status->value, 'action' => $action, 'actor_id' => $actor->id, 'actor_role' => $actor->role?->key ?? 'unknown', 'metadata' => $metadata]);
    }
}
