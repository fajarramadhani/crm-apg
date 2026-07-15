<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Events\TicketAnalysisCompleted;
use App\Events\TicketAnalysisStarted;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Ticket;
use App\Models\TicketAnalysis;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TicketAnalysisService
{
    public function __construct(private TicketTransitionService $transitions) {}

    public function start(Ticket $ticket, User $actor): Ticket
    {
        $fresh = DB::transaction(function () use ($ticket, $actor): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertOwner($locked, $actor);
            $this->transitions->phaseTransition($locked, $actor, TicketStatus::Assigned, TicketStatus::Analysis, 'analysis_started', mutate: fn (Ticket $item) => $item->analysis_started_at = now());

            return $locked->fresh();
        });
        TicketAnalysisStarted::dispatch($fresh, $actor);

        return $fresh;
    }

    public function create(Ticket $ticket, User $actor, array $data): TicketAnalysis
    {
        return DB::transaction(function () use ($ticket, $actor, $data): TicketAnalysis {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertOwner($locked, $actor);
            if ($locked->status !== TicketStatus::Analysis || $locked->current_analysis_id) {
                throw new InvalidTicketTransition($locked->status->value, 'Analysis draft can no longer be created.');
            }
            $analysis = $locked->analyses()->create([...$this->fields($data), 'analyst_id' => $actor->id, 'version' => 1, 'is_current' => true, 'analysis_started_at' => $locked->analysis_started_at ?? now()]);
            $locked->update(['current_analysis_id' => $analysis->id]);
            $this->history($locked, $actor, 'analysis_updated', ['analysis_version' => 1]);

            return $analysis->fresh('analyst');
        });
    }

    public function update(Ticket $ticket, TicketAnalysis $analysis, User $actor, array $data): TicketAnalysis
    {
        return DB::transaction(function () use ($ticket, $analysis, $actor, $data): TicketAnalysis {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $draft = TicketAnalysis::query()->lockForUpdate()->findOrFail($analysis->id);
            $this->assertOwner($locked, $actor);
            if ($draft->ticket_id !== $locked->id || $locked->current_analysis_id !== $draft->id || $locked->status !== TicketStatus::Analysis || $draft->completed_at) {
                throw new InvalidTicketTransition($locked->status->value, 'Analysis is no longer editable.');
            }
            $this->assertFresh($draft->lock_version, $data['expected_lock_version'] ?? null, $locked);
            $draft->update([...$this->fields($data), 'lock_version' => $draft->lock_version + 1]);
            $this->history($locked, $actor, 'analysis_updated', ['analysis_version' => $draft->version]);

            return $draft->fresh('analyst');
        });
    }

    public function complete(Ticket $ticket, TicketAnalysis $analysis, User $actor): TicketAnalysis
    {
        $completed = DB::transaction(function () use ($ticket, $analysis, $actor): TicketAnalysis {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $draft = TicketAnalysis::query()->lockForUpdate()->findOrFail($analysis->id);
            $this->assertOwner($locked, $actor);
            if ($draft->ticket_id !== $locked->id || $locked->current_analysis_id !== $draft->id || $draft->completed_at) {
                throw new InvalidTicketTransition($locked->status->value, 'Analysis is no longer completable.');
            }
            if (! trim((string) $draft->root_cause)) {
                throw ValidationException::withMessages(['root_cause' => ['Root cause is required before analysis can be completed.']]);
            }
            $draft->update(['completed_at' => now()]);
            $this->transitions->phaseTransition($locked, $actor, TicketStatus::Analysis, TicketStatus::SolutionPlanning, 'analysis_completed', metadata: ['analysis_version' => $draft->version], mutate: fn (Ticket $item) => $item->analysis_completed_at = now());

            return $draft->fresh(['ticket', 'analyst']);
        });
        TicketAnalysisCompleted::dispatch($completed->ticket, $actor, $completed);

        return $completed;
    }

    private function fields(array $data): array
    {
        return collect($data)->only(['problem_summary', 'root_cause', 'technical_impact', 'business_impact', 'affected_components', 'evidence', 'assumptions', 'limitations'])->all();
    }

    private function assertOwner(Ticket $ticket, User $actor): void
    {
        if ($ticket->current_assignee_id !== $actor->id || ! $ticket->assignments()->where('assigned_to', $actor->id)->where('is_current', true)->exists()) {
            throw new AuthorizationException;
        }
    }

    private function assertFresh(int $actual, ?int $expected, Ticket $ticket): void
    {
        if (! $expected || $actual !== $expected) {
            throw new InvalidTicketTransition($ticket->status->value, 'The analysis has changed; reload before saving.');
        }
    }

    private function history(Ticket $ticket, User $actor, string $action, array $metadata): void
    {
        $ticket->histories()->create(['from_status' => $ticket->status->value, 'to_status' => $ticket->status->value, 'action' => $action, 'actor_id' => $actor->id, 'actor_role' => $actor->role?->key ?? 'pic', 'metadata' => $metadata]);
    }
}
