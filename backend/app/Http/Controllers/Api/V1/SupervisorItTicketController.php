<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddSecondaryAssigneeRequest;
use App\Http\Requests\Api\V1\AnalyzeTicketRequest;
use App\Http\Requests\Api\V1\AssignPrimaryTicketRequest;
use App\Http\Requests\Api\V1\ReassignTicketRequest;
use App\Http\Requests\Api\V1\SupervisorActionRequest;
use App\Http\Requests\Api\V1\TakeoverTicketRequest;
use App\Http\Requests\Api\V1\TicketListRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\DynamicAssignmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SupervisorItTicketController extends Controller
{
    private const AUDIT_TIMELINE_LIMIT = 100;

    private const RELATIONS = [
        'requester',
        'division',
        'currentDivision',
        'branch',
        'application',
        'applicationModule',
        'category',
        'requestedPriority',
        'finalPriority',
        'slaPolicy',
        'workingCalendar',
        'currentAssignee',
        'assignments.assignee',
        'attachments',
    ];

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole(['supervisor_it', 'supervisor', 'it_lead']) && ! $user->hasPermission('ticket.all.view')) {
            return ApiResponse::error($request, 'Unauthorized to view Supervisor IT dashboard', 'UNAUTHORIZED', 403);
        }

        $now = now();
        $todayStart = $now->copy()->startOfDay();

        // Base query - all tickets (cross division for Supervisor IT)
        $baseQuery = Ticket::query();

        $stats = [
            'new_tickets' => (clone $baseQuery)->whereIn('status', [TicketStatus::PendingValidation, TicketStatus::Draft, TicketStatus::Submitted])->count(),
            'under_analysis' => (clone $baseQuery)->whereIn('status', [TicketStatus::UnderAnalysis, TicketStatus::Triage, TicketStatus::Analysis, TicketStatus::Validated])->count(),
            'unassigned' => (clone $baseQuery)->whereNull('current_assignee_id')->whereNotIn('status', [TicketStatus::Done, TicketStatus::Closed, TicketStatus::Rejected, TicketStatus::Cancelled])->count(),
            'in_progress' => (clone $baseQuery)->whereIn('status', [TicketStatus::Assigned, TicketStatus::InProgress, TicketStatus::DevelopmentInProgress, TicketStatus::ReadyForDevelopment, TicketStatus::InternalTesting])->count(),
            'waiting_info' => (clone $baseQuery)->whereIn('status', [TicketStatus::NeedInfo, TicketStatus::NeedRevision, TicketStatus::Revision])->count(),
            'waiting_external' => (clone $baseQuery)->whereIn('status', [TicketStatus::WaitingExternal, TicketStatus::OnHold])->count(),
            'pending_final_review' => (clone $baseQuery)->whereIn('status', [TicketStatus::PendingApproval, TicketStatus::AwaitingRequesterConfirmation])->count(),
            'overdue' => (clone $baseQuery)
                ->whereNotIn('status', [TicketStatus::Done, TicketStatus::Closed, TicketStatus::Rejected, TicketStatus::Cancelled])
                ->whereNotNull('resolution_due_at')
                ->where('resolution_due_at', '<', $now)
                ->count(),
            'completed_today' => (clone $baseQuery)->whereIn('status', [TicketStatus::Done, TicketStatus::Closed])->where('updated_at', '>=', $todayStart)->count(),
        ];

        // Action required tickets
        $actionRequired = Ticket::query()->forSummary()->with(self::RELATIONS)
            ->whereIn('status', [TicketStatus::PendingValidation, TicketStatus::UnderAnalysis, TicketStatus::PendingApproval, TicketStatus::NeedInfo])
            ->latest()
            ->take(5)
            ->get();

        // High/Critical priority tickets
        $highPriority = Ticket::query()->forSummary()->with(self::RELATIONS)
            ->whereHas('finalPriority', fn ($q) => $q->whereIn('key', ['critical', 'high']))
            ->whereNotIn('status', [TicketStatus::Done, TicketStatus::Closed, TicketStatus::Rejected, TicketStatus::Cancelled])
            ->latest()
            ->take(5)
            ->get();

        // Unassigned tickets
        $unassigned = Ticket::query()->forSummary()->with(self::RELATIONS)
            ->whereNull('current_assignee_id')
            ->whereNotIn('status', [TicketStatus::Done, TicketStatus::Closed, TicketStatus::Rejected, TicketStatus::Cancelled])
            ->latest()
            ->take(5)
            ->get();

        // Overdue tickets
        $overdue = Ticket::query()->forSummary()->with(self::RELATIONS)
            ->whereNotIn('status', [TicketStatus::Done, TicketStatus::Closed, TicketStatus::Rejected, TicketStatus::Cancelled])
            ->whereNotNull('resolution_due_at')
            ->where('resolution_due_at', '<', $now)
            ->latest()
            ->take(5)
            ->get();

        // Pending approval tickets
        $pendingApproval = Ticket::query()->forSummary()->with(self::RELATIONS)
            ->whereIn('status', [TicketStatus::PendingApproval, TicketStatus::AwaitingRequesterConfirmation])
            ->latest()
            ->take(5)
            ->get();

        return ApiResponse::success($request, 'Supervisor IT dashboard retrieved', [
            'stats' => $stats,
            'action_required_tickets' => TicketResource::collection($actionRequired)->resolve($request),
            'high_priority_tickets' => TicketResource::collection($highPriority)->resolve($request),
            'unassigned_tickets' => TicketResource::collection($unassigned)->resolve($request),
            'overdue_tickets' => TicketResource::collection($overdue)->resolve($request),
            'pending_approval_tickets' => TicketResource::collection($pendingApproval)->resolve($request),
        ]);
    }

    public function index(TicketListRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Ticket::class);

        $q = Ticket::query()->forSummary()->with(self::RELATIONS);

        // Search keyword
        $q->when($request->filled('search'), function ($q) use ($request): void {
            $search = '%'.$request->string('search').'%';
            $q->where(function ($q2) use ($search): void {
                $q2->where('ticket_number', 'like', $search)
                    ->orWhere('title', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhereHas('requester', fn ($r) => $r->where('name', 'like', $search));
            });
        });

        // Filters
        $q->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('division_id'), fn ($q) => $q->where('current_division_id', $request->integer('division_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('application_id'), fn ($q) => $q->where('application_id', $request->integer('application_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('ticket_category_id', $request->integer('category_id')))
            ->when($request->filled('priority_id'), fn ($q) => $q->where('final_priority_id', $request->integer('priority_id')))
            ->when($request->filled('priority'), fn ($q) => $q->where('final_priority_id', $request->integer('priority')))
            ->when($request->filled('pic_id'), fn ($q) => $q->where('current_assignee_id', $request->integer('pic_id')))
            ->when($request->boolean('unassigned'), fn ($q) => $q->whereNull('current_assignee_id'))
            ->when($request->boolean('overdue'), function ($q): void {
                $now = now();
                $q->whereNotIn('status', [TicketStatus::Done, TicketStatus::Closed, TicketStatus::Rejected, TicketStatus::Cancelled])
                    ->whereNotNull('resolution_due_at')
                    ->where('resolution_due_at', '<', $now);
            })
            ->when($request->filled('submitted_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('submitted_from')))
            ->when($request->filled('submitted_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('submitted_to')));

        $p = $q->latest()->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            $request,
            'Supervisor IT tickets retrieved',
            TicketResource::collection($p->items())->resolve($request),
            meta: [
                'pagination' => [
                    'current_page' => $p->currentPage(),
                    'per_page' => $p->perPage(),
                    'total' => $p->total(),
                    'last_page' => $p->lastPage(),
                ],
            ]
        );
    }

    public function show(TicketListRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        $auditRelationLimit = self::AUDIT_TIMELINE_LIMIT + 1;
        $ticket->load([
            ...self::RELATIONS,
            'histories' => fn ($query) => $query->reorder()->latest('created_at')->latest('id')->limit($auditRelationLimit),
            'histories.actor',
            'comments' => fn ($query) => $query->reorder()->latest('created_at')->latest('id')->limit($auditRelationLimit),
            'comments.user.role',
            'assignments.assignee.role',
            'assignments.assigner',
            'assignmentHistories' => fn ($query) => $query->reorder()->latest('created_at')->latest('id')->limit($auditRelationLimit),
            'assignmentHistories.actor.role',
            'assignmentHistories.fromUser',
            'assignmentHistories.toUser',
        ]);

        $resolved = (new TicketResource($ticket))->resolve($request);

        // Build unified audit trail timeline
        $timeline = collect();

        // 1. Status histories
        foreach ($ticket->histories as $h) {
            $timeline->push([
                'id' => 'history-'.$h->id,
                'type' => 'status_change',
                'action' => $h->action,
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'actor' => $h->actor ? ['id' => $h->actor->id, 'name' => $h->actor->name] : null,
                'actor_role' => $h->actor_role,
                'notes' => $h->notes,
                'metadata' => $h->metadata,
                'timestamp' => $h->created_at->toISOString(),
            ]);
        }

        // 2. Assignment histories
        foreach ($ticket->assignmentHistories as $ah) {
            $timeline->push([
                'id' => 'assign-'.$ah->id,
                'type' => 'assignment',
                'action' => $ah->action,
                'actor' => $ah->actor ? ['id' => $ah->actor->id, 'name' => $ah->actor->name] : null,
                'actor_role' => $ah->actor ? $ah->actor->role?->key : null,
                'from_user' => $ah->fromUser ? ['id' => $ah->fromUser->id, 'name' => $ah->fromUser->name] : null,
                'to_user' => $ah->toUser ? ['id' => $ah->toUser->id, 'name' => $ah->toUser->name] : null,
                'assignment_type' => $ah->assignment_type,
                'notes' => $ah->notes,
                'reason' => $ah->reason,
                'timestamp' => $ah->created_at->toISOString(),
            ]);
        }

        // 3. Comments & Internal Notes
        foreach ($ticket->comments as $c) {
            $timeline->push([
                'id' => 'comment-'.$c->id,
                'type' => 'comment',
                'action' => $c->type,
                'actor' => $c->user ? ['id' => $c->user->id, 'name' => $c->user->name] : null,
                'actor_role' => $c->user ? $c->user->role?->key : null,
                'comment' => $c->comment,
                'is_internal' => (bool) $c->is_internal,
                'timestamp' => $c->created_at->toISOString(),
            ]);
        }

        $timeline = $timeline->sortByDesc('timestamp')->values();
        $resolved['audit_timeline'] = $timeline->take(self::AUDIT_TIMELINE_LIMIT)->all();
        $resolved['audit_timeline_meta'] = [
            'limit' => self::AUDIT_TIMELINE_LIMIT,
            'has_more' => $timeline->count() > self::AUDIT_TIMELINE_LIMIT,
        ];
        $resolved['is_legacy_workflow'] = $ticket->workflow_mode !== 'simplified' &&
            in_array($ticket->status, [
                TicketStatus::Triage, TicketStatus::PlanReview, TicketStatus::ReadyForQa,
                TicketStatus::QaAssignment, TicketStatus::QaInProgress, TicketStatus::QaFailed, TicketStatus::QaRetest,
                TicketStatus::ReadyForUat, TicketStatus::UatAssignment, TicketStatus::UatInProgress, TicketStatus::UatFailed, TicketStatus::UatRetest,
                TicketStatus::ApprovalPending, TicketStatus::ApprovalRevision, TicketStatus::ReleasePreparation, TicketStatus::ReleaseReady,
            ], true);

        return ApiResponse::success(
            $request,
            'Supervisor IT ticket details retrieved',
            $resolved
        );
    }

    public function assignees(TicketListRequest $request): JsonResponse
    {
        $query = User::eligibleTicketAssignees()
            ->with(['role', 'division', 'branch'])
            ->withCount(['assignedTickets as active_ticket_count' => fn ($query) => $query->whereNotIn('status', [TicketStatus::Done, TicketStatus::Closed, TicketStatus::Rejected, TicketStatus::Cancelled])])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where(fn ($query) => $query->where('name', 'like', $search)->orWhere('email', 'like', $search));
            })
            ->orderBy('name');

        $assignees = $query->paginate(min($request->integer('per_page', 100), 100));
        $data = $assignees->getCollection()->map(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => [
                'id' => $user->role?->id,
                'key' => $user->role?->key,
                'name' => $user->role?->name,
            ],
            'division' => $user->division ? ['id' => $user->division->id, 'name' => $user->division->name] : null,
            'branch' => $user->branch ? ['id' => $user->branch->id, 'name' => $user->branch->name] : null,
            'active_ticket_count' => (int) $user->active_ticket_count,
        ]);

        return ApiResponse::success($request, 'Eligible ticket assignees retrieved', $data, meta: [
            'pagination' => [
                'current_page' => $assignees->currentPage(),
                'per_page' => $assignees->perPage(),
                'total' => $assignees->total(),
                'last_page' => $assignees->lastPage(),
            ],
        ]);
    }

    public function analyze(
        AnalyzeTicketRequest $request,
        Ticket $ticket
    ): JsonResponse {
        Gate::authorize('view', $ticket);

        DB::transaction(function () use ($request, $ticket): void {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            $oldStatus = $locked->status;
            if ($request->filled('target_completion_date')) {
                $locked->resolution_due_at = $request->date('target_completion_date');
            }

            if (in_array($locked->status, [TicketStatus::PendingValidation, TicketStatus::Submitted, TicketStatus::Draft], true)) {
                $locked->status = TicketStatus::UnderAnalysis;
            }
            $locked->analysis_started_at ??= now();

            $locked->save();

            $locked->comments()->create([
                'user_id' => $request->user()->id,
                'type' => 'analysis_summary',
                'comment' => $request->string('analysis_summary'),
                'is_internal' => true,
            ]);

            if ($request->filled('handling_note')) {
                $locked->comments()->create([
                    'user_id' => $request->user()->id,
                    'type' => 'handling_note',
                    'comment' => $request->string('handling_note'),
                    'is_internal' => true,
                ]);
            }

            $locked->histories()->create([
                'from_status' => $oldStatus->value,
                'to_status' => $locked->status->value,
                'action' => 'analyzed',
                'actor_id' => $request->user()->id,
                'actor_role' => $request->user()->role?->key ?? 'supervisor_it',
                'notes' => $request->input('analysis_summary'),
                'metadata' => [
                    'handling_note' => $request->input('handling_note'),
                    'target_completion_date' => $request->input('target_completion_date'),
                ],
            ]);
        });

        $ticket->load([...self::RELATIONS, 'assignments.assignee.role', 'assignmentHistories']);

        return ApiResponse::success(
            $request,
            'Ticket analysis saved successfully',
            (new TicketResource($ticket))->resolve($request)
        );
    }

    public function assignPrimary(
        AssignPrimaryTicketRequest $request,
        Ticket $ticket,
        DynamicAssignmentService $service
    ): JsonResponse {
        Gate::authorize('view', $ticket);

        $targetUser = User::query()->findOrFail($request->integer('user_id'));
        $targetCompletedAt = $request->filled('target_completed_at') ? $request->date('target_completed_at') : null;

        $assignment = $service->assignPrimary(
            $ticket,
            $request->user(),
            $targetUser,
            $request->input('notes'),
            $request->input('reason'),
            $targetCompletedAt
        );

        $ticket->load([...self::RELATIONS, 'assignments.assignee.role', 'assignmentHistories']);

        return ApiResponse::success(
            $request,
            'Primary PIC assigned successfully',
            [
                'ticket' => (new TicketResource($ticket))->resolve($request),
                'assignment_id' => $assignment->id,
            ]
        );
    }

    public function addSecondary(
        AddSecondaryAssigneeRequest $request,
        Ticket $ticket,
        DynamicAssignmentService $service
    ): JsonResponse {
        Gate::authorize('view', $ticket);

        $targetUser = User::query()->findOrFail($request->integer('user_id'));

        $assignment = $service->addSecondary(
            $ticket,
            $request->user(),
            $targetUser,
            $request->input('notes'),
            $request->input('reason')
        );

        $ticket->load([...self::RELATIONS, 'assignments.assignee.role', 'assignmentHistories']);

        return ApiResponse::success(
            $request,
            'Secondary PIC added successfully',
            [
                'ticket' => (new TicketResource($ticket))->resolve($request),
                'assignment_id' => $assignment->id,
            ]
        );
    }

    public function removeSecondary(
        TicketListRequest $request,
        Ticket $ticket,
        User $user,
        DynamicAssignmentService $service
    ): JsonResponse {
        Gate::authorize('view', $ticket);

        $service->removeSecondary(
            $ticket,
            $request->user(),
            $user,
            $request->input('notes'),
            $request->input('reason')
        );

        $ticket->load([...self::RELATIONS, 'assignments.assignee.role', 'assignmentHistories']);

        return ApiResponse::success(
            $request,
            'Secondary PIC removed successfully',
            [
                'ticket' => (new TicketResource($ticket))->resolve($request),
            ]
        );
    }

    public function reassign(
        ReassignTicketRequest $request,
        Ticket $ticket,
        DynamicAssignmentService $service
    ): JsonResponse {
        Gate::authorize('view', $ticket);

        $newPrimaryUser = User::query()->findOrFail($request->integer('user_id'));

        $assignment = $service->reassign(
            $ticket,
            $request->user(),
            $newPrimaryUser,
            $request->input('notes'),
            $request->input('reason')
        );

        $ticket->load([...self::RELATIONS, 'assignments.assignee.role', 'assignmentHistories']);

        return ApiResponse::success(
            $request,
            'Primary PIC reassigned successfully',
            [
                'ticket' => (new TicketResource($ticket))->resolve($request),
                'assignment_id' => $assignment->id,
            ]
        );
    }

    public function takeover(
        TakeoverTicketRequest $request,
        Ticket $ticket,
        DynamicAssignmentService $service
    ): JsonResponse {
        Gate::authorize('view', $ticket);

        $assignment = $service->takeover(
            $ticket,
            $request->user(),
            $request->input('notes'),
            $request->input('reason')
        );

        $ticket->load([...self::RELATIONS, 'assignments.assignee.role', 'assignmentHistories']);

        return ApiResponse::success(
            $request,
            'Ticket taken over successfully',
            [
                'ticket' => (new TicketResource($ticket))->resolve($request),
                'assignment_id' => $assignment->id,
            ]
        );
    }

    public function requestInfo(SupervisorActionRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        DB::transaction(function () use ($request, $ticket): void {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $from = $locked->status;
            $locked->status = TicketStatus::NeedInfo;
            $locked->save();

            $locked->histories()->create([
                'from_status' => $from->value,
                'to_status' => TicketStatus::NeedInfo->value,
                'action' => 'info_requested',
                'actor_id' => $request->user()->id,
                'actor_role' => $request->user()->role?->key ?? 'supervisor_it',
                'notes' => $request->string('notes'),
            ]);

            $locked->comments()->create([
                'user_id' => $request->user()->id,
                'type' => 'info_request',
                'comment' => $request->string('notes'),
                'is_internal' => false,
            ]);
        });

        $ticket->load(self::RELATIONS);

        return ApiResponse::success(
            $request,
            'Information requested from requester',
            (new TicketResource($ticket))->resolve($request)
        );
    }

    public function requestRevision(SupervisorActionRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        DB::transaction(function () use ($request, $ticket): void {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $from = $locked->status;
            $locked->status = TicketStatus::NeedRevision;
            $locked->save();

            $locked->histories()->create([
                'from_status' => $from->value,
                'to_status' => TicketStatus::NeedRevision->value,
                'action' => 'revision_requested',
                'actor_id' => $request->user()->id,
                'actor_role' => $request->user()->role?->key ?? 'supervisor_it',
                'notes' => $request->string('notes'),
            ]);

            $locked->comments()->create([
                'user_id' => $request->user()->id,
                'type' => 'revision_request',
                'comment' => $request->string('notes'),
                'is_internal' => true,
            ]);
        });

        $ticket->load(self::RELATIONS);

        return ApiResponse::success(
            $request,
            'Revision requested from PIC',
            (new TicketResource($ticket))->resolve($request)
        );
    }

    public function approve(SupervisorActionRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        DB::transaction(function () use ($request, $ticket): void {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $selfApproval = $request->user()->hasRole(['supervisor_it', 'supervisor']) && $locked->hasActivePrimaryPic($request->user());
            if ($selfApproval && trim((string) $request->input('notes', '')) === '') {
                throw ValidationException::withMessages([
                    'notes' => ['Catatan wajib diisi untuk self-approval.'],
                ]);
            }

            $from = $locked->status;
            $locked->status = TicketStatus::Done;
            $locked->save();

            $locked->histories()->create([
                'from_status' => $from->value,
                'to_status' => TicketStatus::Done->value,
                'action' => 'approved',
                'actor_id' => $request->user()->id,
                'actor_role' => $request->user()->role?->key ?? 'supervisor_it',
                'notes' => $request->input('notes'),
                'metadata' => [
                    'summary_for_requester' => $request->input('summary_for_requester'),
                    'self_approval' => $selfApproval,
                ],
            ]);

            if ($request->filled('summary_for_requester')) {
                $locked->comments()->create([
                    'user_id' => $request->user()->id,
                    'type' => 'approval_summary',
                    'comment' => $request->string('summary_for_requester'),
                    'is_internal' => false,
                ]);
            }
        });

        $ticket->load(self::RELATIONS);

        return ApiResponse::success(
            $request,
            'Ticket approved successfully',
            (new TicketResource($ticket))->resolve($request)
        );
    }

    public function reject(SupervisorActionRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        DB::transaction(function () use ($request, $ticket): void {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $from = $locked->status;
            $locked->status = TicketStatus::Rejected;
            $locked->rejected_at = now();
            $locked->save();

            $locked->histories()->create([
                'from_status' => $from->value,
                'to_status' => TicketStatus::Rejected->value,
                'action' => 'rejected',
                'actor_id' => $request->user()->id,
                'actor_role' => $request->user()->role?->key ?? 'supervisor_it',
                'notes' => $request->string('reason'),
                'metadata' => [
                    'summary_for_requester' => $request->input('summary_for_requester'),
                ],
            ]);

            $locked->comments()->create([
                'user_id' => $request->user()->id,
                'type' => 'rejection_reason',
                'comment' => $request->filled('summary_for_requester') ? $request->string('summary_for_requester') : $request->string('reason'),
                'is_internal' => false,
            ]);
        });

        $ticket->load(self::RELATIONS);

        return ApiResponse::success(
            $request,
            'Ticket rejected successfully',
            (new TicketResource($ticket))->resolve($request)
        );
    }

    public function cancel(SupervisorActionRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        DB::transaction(function () use ($request, $ticket): void {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $from = $locked->status;
            $locked->status = TicketStatus::Cancelled;
            $locked->save();

            $locked->histories()->create([
                'from_status' => $from->value,
                'to_status' => TicketStatus::Cancelled->value,
                'action' => 'cancelled',
                'actor_id' => $request->user()->id,
                'actor_role' => $request->user()->role?->key ?? 'supervisor_it',
                'notes' => $request->string('reason'),
            ]);

            $locked->comments()->create([
                'user_id' => $request->user()->id,
                'type' => 'cancellation_reason',
                'comment' => $request->string('reason'),
                'is_internal' => false,
            ]);
        });

        $ticket->load(self::RELATIONS);

        return ApiResponse::success(
            $request,
            'Ticket cancelled successfully',
            (new TicketResource($ticket))->resolve($request)
        );
    }

    public function reopen(
        SupervisorActionRequest $request,
        Ticket $ticket,
        DynamicAssignmentService $assignmentService
    ): JsonResponse {
        Gate::authorize('view', $ticket);

        DB::transaction(function () use ($request, $ticket, $assignmentService): void {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $from = $locked->status;
            $locked->status = TicketStatus::Reopened;

            if ($request->filled('target_completion_date')) {
                $locked->resolution_due_at = $request->date('target_completion_date');
                $locked->target_needed_at = $request->date('target_completion_date');
            }

            $locked->save();

            $locked->histories()->create([
                'from_status' => $from->value,
                'to_status' => TicketStatus::Reopened->value,
                'action' => 'reopened',
                'actor_id' => $request->user()->id,
                'actor_role' => $request->user()->role?->key ?? 'supervisor_it',
                'notes' => $request->string('reason'),
            ]);

            $locked->comments()->create([
                'user_id' => $request->user()->id,
                'type' => 'reopen_reason',
                'comment' => $request->string('reason'),
                'is_internal' => false,
            ]);

            if ($request->filled('primary_user_id')) {
                $newPrimary = User::query()->findOrFail($request->integer('primary_user_id'));
                $assignmentService->assignPrimary(
                    $locked,
                    $request->user(),
                    $newPrimary,
                    $request->string('reason'),
                    'Reopened ticket primary PIC assignment'
                );
            }
        });

        $ticket->load(self::RELATIONS);

        return ApiResponse::success(
            $request,
            'Ticket reopened successfully',
            (new TicketResource($ticket))->resolve($request)
        );
    }

    public function close(SupervisorActionRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        DB::transaction(function () use ($request, $ticket): void {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $from = $locked->status;
            $locked->status = TicketStatus::Closed;
            $locked->save();

            $locked->histories()->create([
                'from_status' => $from->value,
                'to_status' => TicketStatus::Closed->value,
                'action' => 'closed',
                'actor_id' => $request->user()->id,
                'actor_role' => $request->user()->role?->key ?? 'supervisor_it',
                'notes' => $request->input('notes'),
            ]);
        });

        $ticket->load(self::RELATIONS);

        return ApiResponse::success(
            $request,
            'Ticket closed successfully',
            (new TicketResource($ticket))->resolve($request)
        );
    }
}
