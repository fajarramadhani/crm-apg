<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Events\TicketDevelopmentProgressUpdated;
use App\Events\TicketDevelopmentStarted;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Ticket;
use App\Models\TicketDevelopmentUpdate;
use App\Models\TicketWorklog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class TicketDevelopmentService
{
    public function __construct(private TicketTransitionService $transitions) {}

    public function start(Ticket $ticket, User $actor): Ticket
    {
        $fresh = DB::transaction(function () use ($ticket, $actor): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertOwner($locked, $actor);
            if (! $locked->currentSolutionPlan?->status || $locked->currentSolutionPlan->status !== 'approved') {
                throw new InvalidTicketTransition($locked->status->value, 'An approved solution plan is required.');
            }
            $this->transitions->phaseTransition($locked, $actor, TicketStatus::ReadyForDevelopment, TicketStatus::DevelopmentInProgress, 'development_started', metadata: ['progress' => 0], mutate: function (Ticket $item): void {
                $item->development_started_at = now();
                $item->progress_percentage = 0;
                $item->latest_progress_at = now();
            });

            return $locked->fresh();
        });
        TicketDevelopmentStarted::dispatch($fresh, $actor);

        return $fresh;
    }

    public function addWorklog(Ticket $ticket, User $actor, array $data): TicketWorklog
    {
        return DB::transaction(function () use ($ticket, $actor, $data): TicketWorklog {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertMutable($locked, $actor);
            $this->assertProgress($locked, (int) $data['expected_progress'], (int) $data['progress_after']);
            $before = $locked->progress_percentage;
            $after = (int) $data['progress_after'];
            $log = $locked->worklogs()->create([...collect($data)->only(['work_date', 'minutes_spent', 'activity_type', 'description'])->all(), 'user_id' => $actor->id, 'progress_before' => $before, 'progress_after' => $after, 'is_internal' => true]);
            if ($after > $before) {
                $locked->update(['progress_percentage' => $after, 'latest_progress_at' => now()]);
            }
            $this->history($locked, $actor, 'worklog_added', ['minutes_spent' => $log->minutes_spent, 'progress' => $after]);

            return $log->fresh('user');
        });
    }

    public function updateProgress(Ticket $ticket, User $actor, array $data): TicketDevelopmentUpdate
    {
        $update = DB::transaction(function () use ($ticket, $actor, $data): TicketDevelopmentUpdate {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertMutable($locked, $actor);
            $this->assertProgress($locked, (int) $data['expected_progress'], (int) $data['progress_percentage']);
            $update = $locked->developmentUpdates()->create([...collect($data)->only(['progress_percentage', 'summary', 'completed_items', 'remaining_items', 'blockers', 'next_steps'])->all(), 'created_by' => $actor->id, 'is_internal' => true]);
            $locked->update(['progress_percentage' => $update->progress_percentage, 'latest_progress_at' => now()]);
            $this->history($locked, $actor, 'development_progress_updated', ['progress' => $update->progress_percentage, 'blocker_count' => count($update->blockers ?? [])]);

            return $update->fresh('creator');
        });
        TicketDevelopmentProgressUpdated::dispatch($ticket->fresh(), $actor);

        return $update;
    }

    private function assertProgress(Ticket $ticket, int $expected, int $after): void
    {
        if ((int) $ticket->progress_percentage !== $expected) {
            throw new InvalidTicketTransition($ticket->status->value, 'Progress has changed; reload before saving.');
        } if ($after < $ticket->progress_percentage) {
            throw new InvalidTicketTransition($ticket->status->value, 'Progress cannot decrease during normal development.');
        }
    }

    public function assertOwner(Ticket $ticket, User $actor): void
    {
        if ($ticket->current_assignee_id !== $actor->id || ! $ticket->assignments()->where('assigned_to', $actor->id)->where('is_current', true)->exists()) {
            throw new AuthorizationException;
        }
    }

    private function assertMutable(Ticket $ticket, User $actor): void
    {
        $this->assertOwner($ticket, $actor);
        if ($ticket->status !== TicketStatus::DevelopmentInProgress) {
            throw new InvalidTicketTransition($ticket->status->value);
        }
    }

    private function history(Ticket $ticket, User $actor, string $action, array $metadata): void
    {
        $ticket->histories()->create(['from_status' => $ticket->status->value, 'to_status' => $ticket->status->value, 'action' => $action, 'actor_id' => $actor->id, 'actor_role' => 'pic', 'metadata' => $metadata]);
    }
}
