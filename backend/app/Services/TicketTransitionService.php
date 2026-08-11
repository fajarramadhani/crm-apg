<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Events\TicketQaAssigned;
use App\Events\TicketQaFailed;
use App\Events\TicketQaRetestSubmitted;
use App\Events\TicketQaStarted;
use App\Events\TicketReadyForUat;
use App\Events\TicketRejected;
use App\Events\TicketReleaseApprovalRequested;
use App\Events\TicketReleasePreparationStarted;
use App\Events\TicketReleaseReady;
use App\Events\TicketResubmitted;
use App\Events\TicketRevisionRequested;
use App\Events\TicketTransferred;
use App\Events\TicketTriageStarted;
use App\Events\TicketUatApproved;
use App\Events\TicketUatAssigned;
use App\Events\TicketUatFailed;
use App\Events\TicketUatRetestSubmitted;
use App\Events\TicketUatStarted;
use App\Events\TicketValidated;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Division;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TicketTransitionService
{
    public function publicPhaseTransition(Ticket $locked, TicketStatus $from, TicketStatus $to, string $action, ?string $notes = null, ?callable $mutate = null): void
    {
        if ($locked->status !== $from) {
            throw new InvalidTicketTransition($locked->status->value);
        }
        $locked->status = $to;
        if ($mutate) {
            $mutate($locked);
        }
        $locked->save();
        $locked->histories()->create([
            'from_status' => $from->value,
            'to_status' => $to->value,
            'action' => $action,
            'actor_id' => null,
            'actor_role' => 'public_requester',
            'notes' => $notes,
        ]);
    }

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

    private function transition(Ticket $ticket, User $actor, TicketStatus $from, TicketStatus $to, string $action, ?string $notes, ?string $commentType, ?callable $mutate, callable $dispatch, ?array $metadata = null): Ticket
    {
        $fresh = DB::transaction(function () use ($ticket, $actor, $from, $to, $action, $notes, $commentType, $mutate, $metadata): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->status !== $from) {
                throw new InvalidTicketTransition($locked->status->value);
            }
            $locked->status = $to;
            if ($mutate) {
                $mutate($locked);
            }
            $locked->save();
            $this->history($locked, $actor, $from, $to, $action, $notes, $metadata);
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

    public function assignUat(Ticket $ticket, User $actor, User $requester, ?string $notes): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::ReadyForUat, TicketStatus::UatAssignment, 'uat_assigned', $notes, null, function (Ticket $locked) use ($requester, $actor): void {
            $locked->uat_assignee_id = $requester->id;
            $locked->uat_assigned_by = $actor->id;
            $locked->uat_assigned_at = now();
        }, function (Ticket $fresh) use ($actor): void {
            TicketUatAssigned::dispatch($fresh, $actor);
        });
    }

    public function startUat(Ticket $ticket, User $actor): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::UatAssignment, TicketStatus::UatInProgress, 'uat_started', null, null, function (Ticket $locked): void {
            $locked->uat_started_at = now();
            if ((int) $locked->uat_cycle_number === 0) {
                $locked->uat_cycle_number = 1;
            }
        }, function (Ticket $fresh) use ($actor): void {
            TicketUatStarted::dispatch($fresh, $actor);
        });
    }

    public function startUatRetest(Ticket $ticket, User $actor): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::UatRetest, TicketStatus::UatInProgress, 'uat_retest_started', null, null, function (Ticket $locked): void {
            $locked->uat_cycle_number = ((int) $locked->uat_cycle_number) + 1;
        }, function (Ticket $fresh) use ($actor): void {
            TicketUatStarted::dispatch($fresh, $actor);
        });
    }

    public function passUat(Ticket $ticket, User $actor, ?string $summary, ?array $metadata): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::UatInProgress, TicketStatus::UatApproved, 'uat_approved', $summary, null, function (Ticket $locked): void {
            $locked->uat_completed_at = now();
            $locked->latest_uat_result = 'accepted';
            $locked->uat_approved_at = now();
        }, function (Ticket $fresh) use ($actor): void {
            TicketUatApproved::dispatch($fresh, $actor);
        });
    }

    public function recordUatFailure(Ticket $ticket, User $actor, ?string $summary, ?array $metadata, ?callable $mutate = null): Ticket
    {
        $fresh = DB::transaction(function () use ($ticket, $actor, $summary, $metadata, $mutate): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->status !== TicketStatus::UatInProgress) {
                throw new InvalidTicketTransition($locked->status->value);
            }

            // First transition: uat_in_progress -> uat_failed
            $locked->status = TicketStatus::UatFailed;
            $locked->latest_uat_result = 'rejected';
            $locked->save();
            $this->history($locked, $actor, TicketStatus::UatInProgress, TicketStatus::UatFailed, 'uat_failed', $summary, $metadata);

            // Second transition: uat_failed -> development_in_progress
            $locked->status = TicketStatus::DevelopmentInProgress;
            if ($mutate) {
                $mutate($locked);
            }
            $locked->save();
            $this->history($locked, $actor, TicketStatus::UatFailed, TicketStatus::DevelopmentInProgress, 'uat_rework_started', $summary, $metadata);

            return $locked->fresh();
        });

        TicketUatFailed::dispatch($fresh, $actor);

        return $fresh;
    }

    public function submitUatRetest(Ticket $ticket, User $actor): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::DevelopmentInProgress, TicketStatus::UatRetest, 'uat_retest_submitted', null, null, function (Ticket $locked): void {
            $locked->progress_percentage = 100;
            $locked->latest_progress_at = now();
        }, function (Ticket $fresh) use ($actor): void {
            TicketUatRetestSubmitted::dispatch($fresh, $actor);
        });
    }

    public function requestReleaseApproval(Ticket $ticket, User $actor, ?string $notes, ?array $metadata = null): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::UatApproved, TicketStatus::ApprovalPending, 'release_approval_requested', $notes, null, function (Ticket $locked): void {
            $locked->approval_requested_at = now();
            $locked->approval_cycle_number = ((int) $locked->approval_cycle_number) + 1;
            $locked->latest_approval_result = 'pending';
        }, function (Ticket $fresh) use ($actor): void {
            TicketReleaseApprovalRequested::dispatch($fresh, $actor);
        }, $metadata);
    }

    public function startReleasePreparation(Ticket $ticket, User $actor): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::ApprovalPending, TicketStatus::ReleasePreparation, 'release_preparation_started', null, null, function (Ticket $locked): void {
            $locked->release_preparation_started_at = now();
        }, function (Ticket $fresh) use ($actor): void {
            TicketReleasePreparationStarted::dispatch($fresh, $actor);
        });
    }

    public function recordApprovalRevision(Ticket $ticket, User $actor, string $reason, ?array $metadata = null): Ticket
    {
        $fresh = DB::transaction(function () use ($ticket, $actor, $reason, $metadata): Ticket {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->status !== TicketStatus::ApprovalPending) {
                throw new InvalidTicketTransition($locked->status->value);
            }
            $locked->status = TicketStatus::ApprovalRevision;
            $locked->latest_approval_result = 'rejected';
            $locked->approval_completed_at = now();
            $locked->save();
            $this->history($locked, $actor, TicketStatus::ApprovalPending, TicketStatus::ApprovalRevision, 'approval_revision_requested', $reason, $metadata);

            $locked->status = TicketStatus::DevelopmentInProgress;
            $locked->progress_percentage = min(90, (int) $locked->progress_percentage);
            $locked->latest_progress_at = now();
            $locked->save();
            $this->history($locked, $actor, TicketStatus::ApprovalRevision, TicketStatus::DevelopmentInProgress, 'approval_rework_started', $reason, $metadata);

            return $locked->fresh();
        });

        return $fresh;
    }

    public function markReleaseReady(Ticket $ticket, User $actor, ?array $metadata = null): Ticket
    {
        return $this->transition($ticket, $actor, TicketStatus::ReleasePreparation, TicketStatus::ReleaseReady, 'release_ready', null, null, function (Ticket $locked): void {
            $locked->release_ready_at = now();
            $locked->approved_for_release_at = now();
        }, function (Ticket $fresh) use ($actor): void {
            TicketReleaseReady::dispatch($fresh, $actor);
        }, $metadata);
    }

    private function history(Ticket $ticket, User $actor, ?TicketStatus $from, TicketStatus $to, string $action, ?string $notes, ?array $metadata = null): void
    {
        $ticket->histories()->create(['from_status' => $from?->value, 'to_status' => $to->value, 'action' => $action, 'actor_id' => $actor->id, 'actor_role' => $actor->role?->key ?? 'unknown', 'notes' => $notes, 'metadata' => $metadata]);
    }
}
