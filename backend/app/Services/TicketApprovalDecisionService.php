<?php

namespace App\Services;

use App\Events\TicketBusinessApprovalApproved;
use App\Events\TicketBusinessApprovalRejected;
use App\Events\TicketTechnicalReadinessApproved;
use App\Events\TicketTechnicalReadinessRejected;
use App\Exceptions\InvalidTicketTransition;
use App\Models\TicketApprovalRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class TicketApprovalDecisionService
{
    public function __construct(private TicketTransitionService $transitions) {}

    public function decide(TicketApprovalRequest $request, string $stepType, User $actor, string $decision, ?string $notes, int $expectedVersion): TicketApprovalRequest
    {
        if ($decision === 'rejected' && blank($notes)) {
            throw new InvalidTicketTransition($request->ticket->status->value, 'Rejection reason is required.');
        }

        return DB::transaction(function () use ($request, $stepType, $actor, $decision, $notes, $expectedVersion): TicketApprovalRequest {
            $lockedRequest = TicketApprovalRequest::query()->lockForUpdate()->with('ticket')->findOrFail($request->id);
            if ($lockedRequest->status !== 'pending') {
                throw new InvalidTicketTransition($lockedRequest->ticket->status->value, 'Approval request is no longer pending.');
            }
            $step = $lockedRequest->steps()->lockForUpdate()->where('step_type', $stepType)->firstOrFail();
            if ($step->approver_id !== $actor->id) {
                throw new AuthorizationException('This approval step is assigned to another approver.');
            }
            if ($step->status !== 'pending' || $step->version !== $expectedVersion) {
                throw new InvalidTicketTransition($lockedRequest->ticket->status->value, 'Approval step is stale or already decided.');
            }
            $from = $step->status;
            $step->update(['status' => $decision, 'acted_at' => now(), 'decision_notes' => $notes, 'version' => $step->version + 1]);
            $lockedRequest->histories()->create(['approval_step_id' => $step->id, 'action' => $this->action($stepType, $decision), 'actor_id' => $actor->id, 'from_status' => $from, 'to_status' => $decision, 'notes' => $notes, 'created_at' => now()]);

            if ($decision === 'approved') {
                $event = $stepType === 'business_approval' ? TicketBusinessApprovalApproved::class : TicketTechnicalReadinessApproved::class;
                $event::dispatch($lockedRequest->ticket, $actor);
            }

            if ($decision === 'rejected') {
                $lockedRequest->steps()->where('status', 'pending')->update(['status' => 'cancelled', 'version' => DB::raw('version + 1')]);
                $lockedRequest->update(['status' => 'rejected', 'completed_at' => now(), 'revision_reason' => $notes, 'version' => $lockedRequest->version + 1]);
                $lockedRequest->histories()->create(['action' => 'approval_revision_requested', 'actor_id' => $actor->id, 'from_status' => 'pending', 'to_status' => 'rejected', 'notes' => $notes, 'created_at' => now()]);
                $ticket = $this->transitions->recordApprovalRevision($lockedRequest->ticket, $actor, $notes, ['approval_cycle' => $lockedRequest->cycle_number]);
                $event = $stepType === 'business_approval' ? TicketBusinessApprovalRejected::class : TicketTechnicalReadinessRejected::class;
                $event::dispatch($ticket, $actor);
            } elseif ($lockedRequest->steps()->where('status', '!=', 'approved')->doesntExist()) {
                $lockedRequest->update(['status' => 'approved', 'completed_at' => now(), 'version' => $lockedRequest->version + 1]);
                $lockedRequest->ticket()->update(['approval_completed_at' => now(), 'latest_approval_result' => 'approved']);
                $ticket = $this->transitions->startReleasePreparation($lockedRequest->ticket, $actor);
            }

            return $lockedRequest->fresh(['steps.approver', 'ticket']);
        });
    }

    private function action(string $stepType, string $decision): string
    {
        return match ([$stepType, $decision]) {
            ['business_approval', 'approved'] => 'business_approved',
            ['business_approval', 'rejected'] => 'business_rejected',
            ['technical_readiness', 'approved'] => 'technical_approved',
            default => 'technical_rejected',
        };
    }
}
