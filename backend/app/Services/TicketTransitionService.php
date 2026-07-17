<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Events\TicketQaAssigned;
use App\Events\TicketQaFailed;
use App\Events\TicketQaRetestSubmitted;
use App\Events\TicketQaStarted;
use App\Events\TicketReadyForUat;
use App\Events\TicketRejected;
use App\Events\TicketResubmitted;
use App\Events\TicketRevisionRequested;
use App\Events\TicketTransferred;
use App\Events\TicketTriageStarted;
use App\Events\TicketValidated;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Division;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TicketTransitionService
{
    public function phaseTransition(Ticket $locked, User $actor, TicketStatus $from, TicketStatus $to, string $action, ?string $notes = null, ?array $metadata = null, ?callable $mutate = null): void
    {
        if ($locked->status !== $from) {
            throw new InvalidTicketTransition($locked->status->value);
        }
        $locked->status = $to;
        if ($mutate) {
            $mutate($locked);
        }
        $locked->save();
        $this->history($locked, $actor, $from, $to, $action, $notes, $metadata);
    }

    public function startTriage(Ticket $ticket, User $actor): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::Validated, TicketStatus::Triage, 'triage_started', null, null, function (Ticket $locked): void {
            $locked->triage_started_at = now();
        }, fn (Ticket $fresh) => TicketTriageStarted::dispatch($fresh, $actor));
    }

    public function validate(Ticket $ticket, User $actor, ?string $notes): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::PendingValidation, TicketStatus::Validated, 'validated', $notes, null, function (Ticket $locked): void {
            $locked->validated_at = now();
        }, fn (Ticket $fresh) => TicketValidated::dispatch($fresh));
    }

    public function requestRevision(Ticket $ticket, User $actor, string $reason): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::PendingValidation, TicketStatus::NeedRevision, 'revision_requested', $reason, 'revision_request', null, fn (Ticket $fresh) => TicketRevisionRequested::dispatch($fresh));
    }

    public function reject(Ticket $ticket, User $actor, string $reason): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::PendingValidation, TicketStatus::Rejected, 'rejected', $reason, 'rejection_reason', function (Ticket $locked): void {
            $locked->rejected_at = now();
        }, fn (Ticket $fresh) => TicketRejected::dispatch($fresh));
    }

    public function resubmit(Ticket $ticket, User $actor, ?string $note): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::NeedRevision, TicketStatus::PendingValidation, 'resubmitted', $note, $note ? 'resubmission_note' : null, function (Ticket $locked): void {
            $locked->submitted_at = now();
        }, fn (Ticket $fresh) => TicketResubmitted::dispatch($fresh));
    }

    public function cancel(Ticket $ticket, User $actor, ?string $reason): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor, $reason): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if (! in_array($locked->status, [TicketStatus::Draft, TicketStatus::PendingValidation, TicketStatus::NeedRevision], true)) {
                throw new InvalidTicketTransition($locked->status->value);
            }
            $from = $locked->status;
            $locked->status = TicketStatus::Cancelled;
            $locked->save();
            $this->history($locked, $actor, $from, TicketStatus::Cancelled, 'cancelled', $reason);

            return $locked->fresh();
        });
    }

    public function transfer(Ticket $ticket, User $actor, Division $target, string $reason): Ticket
    {
        $fresh = DB::transaction(function () use ($ticket, $actor, $target, $reason): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->status !== TicketStatus::PendingValidation) {
                throw new InvalidTicketTransition($locked->status->value);
            }
            if (! $target->is_active || $target->id === $locked->current_division_id) {
                throw new InvalidTicketTransition($locked->status->value, 'Target division is invalid.');
            }
            $fromDivision = $locked->current_division_id;
            $locked->current_division_id = $target->id;
            $locked->save();
            $this->history($locked, $actor, TicketStatus::PendingValidation, TicketStatus::PendingValidation, 'transferred', $reason, ['from_division_id' => $fromDivision, 'to_division_id' => $target->id]);
            $locked->comments()->create(['user_id' => $actor->id, 'type' => 'transfer_note', 'comment' => $reason, 'is_internal' => false]);

            return $locked->fresh();
        });
        TicketTransferred::dispatch($fresh);

        return $fresh;
    }

    private function transition(Ticket $ticket, User $actor, TicketStatus $from, TicketStatus $to, string $action, ?string $notes, ?string $commentType, ?callable $mutate, callable $dispatch): Ticket
    {
        $fresh = DB::transaction(function () use ($ticket, $actor, $from, $to, $action, $notes, $commentType, $mutate): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->status !== $from) {
                throw new InvalidTicketTransition($locked->status->value);
            }
            $locked->status = $to;
            if ($mutate) {
                $mutate($locked);
            }
            $locked->save();
            $this->history($locked, $actor, $from, $to, $action, $notes);
            if ($commentType && $notes) {
                $locked->comments()->create(['user_id' => $actor->id, 'type' => $commentType, 'comment' => $notes, 'is_internal' => false]);
            }

            return $locked->fresh();
        });
        $dispatch($fresh);

        return $fresh;
    }

    public function assignQa(Ticket $ticket, User $actor, User $qaUser, ?string $notes): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::ReadyForQa, TicketStatus::QaAssignment, 'qa_assigned', $notes, null, function (Ticket $locked) use ($qaUser, $actor): void {
            $locked->qa_assignee_id = $qaUser->id;
            $locked->qa_assigned_by = $actor->id;
            $locked->qa_assigned_at = now();
        }, function (Ticket $fresh) use ($actor): void {
            TicketQaAssigned::dispatch($fresh, $actor);
        });
    }

    public function startQa(Ticket $ticket, User $actor): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::QaAssignment, TicketStatus::QaInProgress, 'qa_started', null, null, function (Ticket $locked): void {
            $locked->qa_started_at = now();
            if ((int) $locked->qa_cycle_number === 0) {
                $locked->qa_cycle_number = 1;
            }
        }, function (Ticket $fresh) use ($actor): void {
            TicketQaStarted::dispatch($fresh, $actor);
        });
    }

    public function startQaRetest(Ticket $ticket, User $actor): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::QaRetest, TicketStatus::QaInProgress, 'qa_retest_started', null, null, function (Ticket $locked): void {
            $locked->qa_cycle_number = ((int) $locked->qa_cycle_number) + 1;
        }, function (Ticket $fresh) use ($actor): void {
            TicketQaStarted::dispatch($fresh, $actor);
        });
    }

    public function passQa(Ticket $ticket, User $actor, ?string $summary, ?array $metadata): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::QaInProgress, TicketStatus::ReadyForUat, 'qa_passed', $summary, null, function (Ticket $locked): void {
            $locked->qa_completed_at = now();
            $locked->latest_qa_result = 'passed';
            $locked->ready_for_uat_at = now();
        }, function (Ticket $fresh) use ($actor): void {
            TicketReadyForUat::dispatch($fresh, $actor);
        });
    }

    public function recordQaFailure(Ticket $ticket, User $actor, ?string $summary, ?array $metadata, ?callable $mutate = null): Ticket
    {
        $fresh = DB::transaction(function () use ($ticket, $actor, $summary, $metadata, $mutate): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->status !== TicketStatus::QaInProgress) {
                throw new InvalidTicketTransition($locked->status->value);
            }

            // First transition: qa_in_progress -> qa_failed
            $locked->status = TicketStatus::QaFailed;
            $locked->latest_qa_result = 'failed';
            $locked->save();
            $this->history($locked, $actor, TicketStatus::QaInProgress, TicketStatus::QaFailed, 'qa_failed', $summary, $metadata);

            // Second transition: qa_failed -> development_in_progress
            $locked->status = TicketStatus::DevelopmentInProgress;
            if ($mutate) {
                $mutate($locked);
            }
            $locked->save();
            $this->history($locked, $actor, TicketStatus::QaFailed, TicketStatus::DevelopmentInProgress, 'qa_rework_started', $summary, $metadata);

            return $locked->fresh();
        });

        TicketQaFailed::dispatch($fresh, $actor);

        return $fresh;
    }

    public function submitQaRetest(Ticket $ticket, User $actor): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::DevelopmentInProgress, TicketStatus::QaRetest, 'qa_retest_submitted', null, null, function (Ticket $locked): void {
            $locked->progress_percentage = 100;
            $locked->latest_progress_at = now();
        }, function (Ticket $fresh) use ($actor): void {
            TicketQaRetestSubmitted::dispatch($fresh, $actor);
        });
    }

    private function history(Ticket $ticket, User $actor, ?TicketStatus $from, TicketStatus $to, string $action, ?string $notes, ?array $metadata = null): void
    {
        $ticket->histories()->create(['from_status' => $from?->value, 'to_status' => $to->value, 'action' => $action, 'actor_id' => $actor->id, 'actor_role' => $actor->role?->key ?? 'unknown', 'notes' => $notes, 'metadata' => $metadata]);
    }
}
