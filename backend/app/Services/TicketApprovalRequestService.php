<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketTransition;
use App\Models\Ticket;
use App\Models\TicketApprovalRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TicketApprovalRequestService
{
    public function __construct(private TicketTransitionService $transitions) {}

    public function request(Ticket $ticket, User $actor, array $data): TicketApprovalRequest
    {
        return DB::transaction(function () use ($ticket, $actor, $data): TicketApprovalRequest {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->status !== TicketStatus::UatApproved) {
                throw new InvalidTicketTransition($locked->status->value, 'Release approval requires UAT approval.');
            }
            if ($locked->approvalRequests()->whereIn('status', ['draft', 'pending'])->exists()) {
                throw new InvalidTicketTransition($locked->status->value, 'An approval request is already active.');
            }

            $manager = User::query()->where('role_id', function ($query): void {
                $query->select('id')->from('roles')->where('key', 'manager');
            })->where('division_id', $locked->division_id)->where('is_active', true)->first();
            if (! $manager) {
                throw new InvalidTicketTransition($locked->status->value, 'No active manager is available for the ticket division.');
            }
            $technical = $actor->hasRole('it_lead') ? $actor : User::query()->whereHas('role', fn ($q) => $q->where('key', 'it_lead'))->where('is_active', true)->first();
            if (! $technical) {
                throw new InvalidTicketTransition($locked->status->value, 'No active IT Lead is available for technical review.');
            }

            $updated = $this->transitions->requestReleaseApproval($locked, $actor, $data['summary'] ?? null, ['release_risk_level' => $data['release_risk_level']]);
            $request = $updated->approvalRequests()->create([
                'cycle_number' => $updated->approval_cycle_number,
                'requested_by' => $actor->id,
                'requested_at' => now(),
                'status' => 'pending',
                'summary' => $data['summary'],
                'business_impact' => $data['business_impact'] ?? null,
                'release_risk_level' => $data['release_risk_level'],
                'proposed_release_at' => $data['proposed_release_at'] ?? null,
                'version' => 1,
            ]);
            $request->steps()->createMany([
                ['step_type' => 'business_approval', 'sequence' => 1, 'approver_id' => $manager->id, 'status' => 'pending', 'assigned_at' => now(), 'version' => 1],
                ['step_type' => 'technical_readiness', 'sequence' => 2, 'approver_id' => $technical->id, 'status' => 'pending', 'assigned_at' => now(), 'version' => 1],
            ]);
            $this->history($request, 'approval_requested', $actor, null, 'pending', $data['summary'] ?? null, ['cycle_number' => $request->cycle_number]);

            return $request->fresh(['steps.approver', 'ticket']);
        });
    }

    private function history(TicketApprovalRequest $request, string $action, User $actor, ?string $from, string $to, ?string $notes, ?array $metadata = null): void
    {
        $request->histories()->create(['action' => $action, 'actor_id' => $actor->id, 'from_status' => $from, 'to_status' => $to, 'notes' => $notes, 'metadata' => $metadata, 'created_at' => now()]);
    }
}
