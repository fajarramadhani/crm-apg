<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PicActionRequest;
use App\Http\Requests\Api\V1\SaveTicketAnalysisRequest;
use App\Http\Requests\Api\V1\SaveTicketSolutionPlanRequest;
use App\Http\Requests\Api\V1\TicketListRequest;
use App\Http\Resources\Api\V1\TicketAnalysisResource;
use App\Http\Resources\Api\V1\TicketAttachmentResource;
use App\Http\Resources\Api\V1\TicketResource;
use App\Http\Resources\Api\V1\TicketSolutionPlanResource;
use App\Models\Ticket;
use App\Models\TicketAnalysis;
use App\Models\TicketComment;
use App\Models\TicketSolutionPlan;
use App\Services\TicketAnalysisService;
use App\Services\TicketAttachmentService;
use App\Services\TicketNotificationService;
use App\Services\TicketSolutionPlanService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class PicTicketController extends Controller
{
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
        'attachments',
        'assignments.assignee',
        'assignments.assigner',
        'comments.user',
        'histories.actor',
    ];

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get base query for tickets assigned to user with active assignment
        $assignedTickets = Ticket::query()->forSummary()
            ->whereHas('assignments', fn ($q) => $q->where('assigned_to', $user->id)->where('is_current', true))
            ->with(self::RELATIONS);

        $now = Carbon::now();

        // Statistics
        $summaryCards = [
            'new_assigned' => (clone $assignedTickets)->whereIn('status', [TicketStatus::Assigned->value, TicketStatus::UnderAnalysis->value, TicketStatus::InProgress->value])
                ->where(fn ($q) => $q->whereNull('progress_percentage')->orWhere('progress_percentage', 0))
                ->count(),
            'in_progress' => (clone $assignedTickets)->whereIn('status', [TicketStatus::Assigned->value, TicketStatus::InProgress->value, TicketStatus::DevelopmentInProgress->value])
                ->where('progress_percentage', '>', 0)
                ->count(),
            'waiting_info' => (clone $assignedTickets)->where('status', TicketStatus::NeedInfo->value)->count(),
            'waiting_external' => (clone $assignedTickets)->where('status', TicketStatus::WaitingExternal->value)->count(),
            'need_revision' => (clone $assignedTickets)->whereIn('status', [TicketStatus::Revision->value, TicketStatus::NeedRevision->value])->count(),
            'nearing_due' => (clone $assignedTickets)->whereNotIn('status', [TicketStatus::Done->value, TicketStatus::Closed->value, TicketStatus::Rejected->value, TicketStatus::Cancelled->value])
                ->whereNotNull('resolution_due_at')
                ->whereBetween('resolution_due_at', [$now, $now->copy()->addHours(24)])
                ->count(),
            'overdue' => (clone $assignedTickets)->whereNotIn('status', [TicketStatus::Done->value, TicketStatus::Closed->value, TicketStatus::Rejected->value, TicketStatus::Cancelled->value])
                ->whereNotNull('resolution_due_at')
                ->where('resolution_due_at', '<', $now)
                ->count(),
            'pending_supervisor_check' => (clone $assignedTickets)->whereIn('status', [TicketStatus::PendingApproval->value, TicketStatus::ApprovalPending->value])->count(),
        ];

        // Highlight lists
        $latestAssigned = (clone $assignedTickets)->latest('assigned_at')->limit(5)->get();
        $highPriority = (clone $assignedTickets)->whereHas('finalPriority', fn ($q) => $q->whereIn('key', ['high', 'urgent']))
            ->whereNotIn('status', [TicketStatus::Done->value, TicketStatus::Closed->value, TicketStatus::Rejected->value, TicketStatus::Cancelled->value])
            ->latest()
            ->limit(5)
            ->get();
        $nearingDueTickets = (clone $assignedTickets)->whereNotIn('status', [TicketStatus::Done->value, TicketStatus::Closed->value, TicketStatus::Rejected->value, TicketStatus::Cancelled->value])
            ->whereNotNull('resolution_due_at')
            ->where('resolution_due_at', '>=', $now)
            ->orderBy('resolution_due_at', 'asc')
            ->limit(5)
            ->get();
        $revisionRequestedTickets = (clone $assignedTickets)->whereIn('status', [TicketStatus::Revision->value, TicketStatus::NeedRevision->value])
            ->latest()
            ->limit(5)
            ->get();
        $actionRequiredTickets = (clone $assignedTickets)->whereIn('status', [TicketStatus::Assigned->value, TicketStatus::InProgress->value, TicketStatus::Revision->value, TicketStatus::NeedRevision->value])
            ->latest()
            ->limit(5)
            ->get();

        return ApiResponse::success($request, 'PIC dashboard summary retrieved', [
            'summary_cards' => $summaryCards,
            'latest_assigned' => TicketResource::collection($latestAssigned)->resolve($request),
            'high_priority' => TicketResource::collection($highPriority)->resolve($request),
            'nearing_due_tickets' => TicketResource::collection($nearingDueTickets)->resolve($request),
            'revision_requested_tickets' => TicketResource::collection($revisionRequestedTickets)->resolve($request),
            'action_required_tickets' => TicketResource::collection($actionRequiredTickets)->resolve($request),
        ]);
    }

    public function tickets(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Ticket::query()->forSummary()
            ->whereHas('assignments', fn ($q) => $q->where('assigned_to', $user->id)->where('is_current', true))
            ->with(self::RELATIONS);

        // Search keyword
        if ($request->filled('keyword')) {
            $kw = $request->string('keyword');
            $query->where(function ($q) use ($kw): void {
                $q->where('ticket_number', 'like', "%{$kw}%")
                    ->orWhere('title', 'like', "%{$kw}%")
                    ->orWhere('description', 'like', "%{$kw}%")
                    ->orWhereHas('requester', fn ($rq) => $rq->where('name', 'like', "%{$kw}%"));
            });
        }

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('application_id')) {
            $query->where('application_id', $request->integer('application_id'));
        }

        if ($request->filled('ticket_category_id')) {
            $query->where('ticket_category_id', $request->integer('ticket_category_id'));
        }

        if ($request->filled('priority')) {
            $query->where('final_priority_id', $request->integer('priority'));
        }

        if ($request->filled('assignment_role')) {
            $role = $request->string('assignment_role');
            $query->whereHas('assignments', fn ($q) => $q->where('assigned_to', $user->id)->where('is_current', true)->where('assignment_type', $role));
        }

        $now = Carbon::now();
        if ($request->boolean('near_due')) {
            $query->whereNotIn('status', [TicketStatus::Done->value, TicketStatus::Closed->value, TicketStatus::Rejected->value, TicketStatus::Cancelled->value])
                ->whereNotNull('resolution_due_at')
                ->whereBetween('resolution_due_at', [$now, $now->copy()->addHours(24)]);
        }

        if ($request->boolean('overdue')) {
            $query->whereNotIn('status', [TicketStatus::Done->value, TicketStatus::Closed->value, TicketStatus::Rejected->value, TicketStatus::Cancelled->value])
                ->whereNotNull('resolution_due_at')
                ->where('resolution_due_at', '<', $now);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->string('date_to'));
        }

        $paginated = $query->latest('assigned_at')->paginate(min($request->integer('per_page', 20), 100));

        return ApiResponse::success(
            $request,
            'PIC tickets retrieved',
            TicketResource::collection($paginated->items())->resolve($request),
            meta: [
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ]
        );
    }

    public function index(TicketListRequest $request): JsonResponse
    {
        return $this->tickets($request);
    }

    public function show(TicketListRequest $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        // Enforce active assignment check
        $hasActiveAssignment = $ticket->assignments()
            ->where('assigned_to', $user->id)
            ->where('is_current', true)
            ->exists();

        if (! $hasActiveAssignment && ! $user->hasRole('supervisor_it')) {
            abort(403, 'Anda tidak memiliki assignment aktif untuk tiket ini.');
        }

        $ticket->load([
            ...self::RELATIONS,
            'histories.actor',
            'comments.user',
            'assignments.assignee',
            'assignments.assigner',
            'attachments.uploader',
        ]);

        // Attach active user assignment info
        $currentAssignment = $ticket->assignments
            ->where('assigned_to', $user->id)
            ->where('is_current', true)
            ->first();

        $data = (new TicketResource($ticket))->resolve($request);
        $data['user_assignment_role'] = $currentAssignment?->assignment_type ?? ($user->hasRole('supervisor_it') ? 'supervisor' : null);
        $data['active_primary_pic'] = $ticket->assignments->where('assignment_type', 'primary')->where('is_current', true)->first()?->assignee;
        $data['active_secondary_pics'] = $ticket->assignments->where('assignment_type', 'secondary')->where('is_current', true)->map(fn ($a) => $a->assignee)->values();
        $data['is_legacy_workflow'] = $ticket->workflow_mode !== 'simplified' && (
            ($data['solution_plan_summary']['status'] ?? null) === 'approved' ||
            in_array('start_qa', $data['allowed_actions'], true)
        );
        $data['allowed_actions'] = $this->workspaceAllowedActions($ticket, $currentAssignment?->assignment_type, $user->hasRole('supervisor_it'));

        return ApiResponse::success($request, 'PIC ticket retrieved', $data);
    }

    public function start(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        // Verify active assignment
        $primaryAssignment = $ticket->assignments()
            ->where('assigned_to', $user->id)
            ->where('is_current', true)
            ->where('assignment_type', 'primary')
            ->first();

        if (! $primaryAssignment && ! $user->hasRole('supervisor_it')) {
            // Check if user is secondary PIC
            $isSecondary = $ticket->assignments()
                ->where('assigned_to', $user->id)
                ->where('is_current', true)
                ->where('assignment_type', 'secondary')
                ->exists();

            if ($isSecondary) {
                return ApiResponse::success($request, 'Secondary PIC dapat langsung menambahkan catatan dan progres tanpa mengubah status utama.', (new TicketResource($ticket->fresh(self::RELATIONS)))->resolve($request));
            }

            abort(403, 'Hanya Primary PIC yang dapat mengubah status mulai pengerjaan.');
        }

        if (in_array($ticket->status, [TicketStatus::Done, TicketStatus::Closed, TicketStatus::Rejected, TicketStatus::Cancelled], true)) {
            abort(409, 'Tiket sudah selesai, ditolak, atau dibatalkan.');
        }

        DB::transaction(function () use ($ticket, $user): void {
            $now = Carbon::now();
            $fromStatus = $ticket->status->value;

            $ticket->fill([
                'status' => TicketStatus::InProgress,
                'development_started_at' => $ticket->development_started_at ?? $now,
            ])->save();

            $ticket->histories()->create([
                'actor_id' => $user->id,
                'actor_role' => $user->role?->key ?? 'pic_it_support',
                'action' => 'started',
                'from_status' => $fromStatus,
                'to_status' => TicketStatus::InProgress->value,
                'notes' => 'PIC memulai pengerjaan tiket.',
                'created_at' => $now,
            ]);
        });

        return ApiResponse::success($request, 'Pengerjaan tiket telah dimulai.', (new TicketResource($ticket->fresh(self::RELATIONS)))->resolve($request));
    }

    public function addWorkNote(PicActionRequest $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        $this->ensureActiveAssignment($ticket, $user);

        $validated = $request->validated();
        $visibility = $validated['visibility'] ?? 'internal';
        $isInternal = $visibility !== 'requester_visible';

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'type' => 'work_note',
            'comment' => $validated['content'],
            'is_internal' => $isInternal,
        ]);

        return ApiResponse::success($request, 'Catatan pekerjaan berhasil ditambahkan.', [
            'id' => $comment->id,
            'comment' => $comment->comment,
            'is_internal' => $comment->is_internal,
            'user' => ['id' => $user->id, 'name' => $user->name],
            'created_at' => $comment->created_at->toISOString(),
        ], 201);
    }

    public function uploadAttachment(PicActionRequest $request, Ticket $ticket, TicketAttachmentService $attachments): JsonResponse
    {
        $user = $request->user();
        $this->ensureActiveAssignment($ticket, $user);

        $file = $request->file('file');
        $visibility = $request->input('visibility', 'internal');
        $category = $request->input('category', 'result');
        $attachment = $attachments->store($ticket, $file, $user->id, $category, $visibility, directory: "tickets/{$ticket->id}/pic");

        return ApiResponse::success($request, 'Lampiran berhasil diunggah.', (new TicketAttachmentResource($attachment))->resolve($request), 201);
    }

    public function updateProgress(PicActionRequest $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        $this->ensureActiveAssignment($ticket, $user);

        $validated = $request->validated();
        $percentage = (int) $validated['progress_percentage'];
        $notes = $validated['notes'] ?? null;

        DB::transaction(function () use ($ticket, $user, $percentage, $notes): void {
            $now = Carbon::now();
            $currentStatus = $ticket->status->value;

            $ticket->fill([
                'progress_percentage' => $percentage,
                'latest_progress_at' => $now,
            ])->save();

            if ($notes) {
                TicketComment::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'type' => 'progress_update',
                    'comment' => "Progress {$percentage}%: {$notes}",
                    'is_internal' => true,
                ]);
            }

            $ticket->histories()->create([
                'actor_id' => $user->id,
                'actor_role' => $user->role?->key ?? 'pic_it_support',
                'action' => 'progress_updated',
                'from_status' => $currentStatus,
                'to_status' => $currentStatus,
                'notes' => "Progres diperbarui menjadi {$percentage}%".($notes ? ": {$notes}" : '.'),
                'created_at' => $now,
            ]);
        });

        return ApiResponse::success($request, 'Progres pekerjaan berhasil diperbarui.', (new TicketResource($ticket->fresh(self::RELATIONS)))->resolve($request));
    }

    public function requestInfo(PicActionRequest $request, Ticket $ticket, TicketNotificationService $notificationService): JsonResponse
    {
        $user = $request->user();

        // Primary PIC or Supervisor only
        $this->ensurePrimaryPicOrSupervisor($ticket, $user);

        $validated = $request->validated();
        $question = $validated['question'];

        DB::transaction(function () use ($ticket, $user, $question, $notificationService): void {
            $now = Carbon::now();
            $fromStatus = $ticket->status->value;

            TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'type' => 'info_request',
                'comment' => $question,
                'is_internal' => false,
            ]);

            $ticket->fill([
                'status' => TicketStatus::NeedInfo,
            ])->save();

            $ticket->histories()->create([
                'actor_id' => $user->id,
                'actor_role' => $user->role?->key ?? 'pic_it_support',
                'action' => 'info_requested',
                'from_status' => $fromStatus,
                'to_status' => TicketStatus::NeedInfo->value,
                'notes' => "PIC meminta informasi ke Requester: {$question}",
                'created_at' => $now,
            ]);

            // Notify Requester
            $notificationService->dispatch(
                $ticket,
                'requester_confirmation_required',
                'warning',
                'Permintaan Informasi Tiket #'.$ticket->ticket_number,
                'PIC membutuhkan informasi tambahan mengenai tiket Anda: '.$question,
                "/requester/tickets/{$ticket->id}",
                $user->id
            );
        });

        return ApiResponse::success($request, 'Permintaan informasi berhasil dikirim kepada Requester.', (new TicketResource($ticket->fresh(self::RELATIONS)))->resolve($request));
    }

    public function markWaitingExternal(PicActionRequest $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        $this->ensureActiveAssignment($ticket, $user);

        $validated = $request->validated();

        DB::transaction(function () use ($ticket, $user, $validated): void {
            $now = Carbon::now();
            $fromStatus = $ticket->status->value;
            $extParty = $validated['external_party_name'];
            $refNum = $validated['reference_number'] ?? '-';
            $notes = $validated['notes'];

            TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'type' => 'waiting_external',
                'comment' => "Menunggu Pihak Eksternal ({$extParty}, Ref: {$refNum}): {$notes}",
                'is_internal' => true,
            ]);

            $ticket->fill([
                'status' => TicketStatus::WaitingExternal,
            ])->save();

            $ticket->histories()->create([
                'actor_id' => $user->id,
                'actor_role' => $user->role?->key ?? 'pic_it_support',
                'action' => 'marked_waiting_external',
                'from_status' => $fromStatus,
                'to_status' => TicketStatus::WaitingExternal->value,
                'notes' => "Tiket ditandai menunggu pihak eksternal: {$extParty}",
                'created_at' => $now,
            ]);
        });

        return ApiResponse::success($request, 'Tiket ditandai menunggu pihak eksternal.', (new TicketResource($ticket->fresh(self::RELATIONS)))->resolve($request));
    }

    public function resume(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        $this->ensureActiveAssignment($ticket, $user);

        if (! in_array($ticket->status, [TicketStatus::NeedInfo, TicketStatus::WaitingExternal, TicketStatus::OnHold], true)) {
            abort(409, 'Tiket tidak dalam kondisi menunggu yang dapat dilanjutkan.');
        }

        DB::transaction(function () use ($ticket, $user): void {
            $now = Carbon::now();
            $fromStatus = $ticket->status->value;

            $ticket->fill([
                'status' => TicketStatus::InProgress,
            ])->save();

            $ticket->histories()->create([
                'actor_id' => $user->id,
                'actor_role' => $user->role?->key ?? 'pic_it_support',
                'action' => 'resumed',
                'from_status' => $fromStatus,
                'to_status' => TicketStatus::InProgress->value,
                'notes' => 'Pengerjaan tiket dilanjutkan.',
                'created_at' => $now,
            ]);
        });

        return ApiResponse::success($request, 'Pengerjaan tiket berhasil dilanjutkan.', (new TicketResource($ticket->fresh(self::RELATIONS)))->resolve($request));
    }

    public function internalCheck(PicActionRequest $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        $this->ensureActiveAssignment($ticket, $user);

        $validated = $request->validated();
        $result = $validated['result']; // 'passed' or 'needs_rework'
        $notes = $validated['notes'];

        DB::transaction(function () use ($ticket, $user, $result, $notes): void {
            $now = Carbon::now();
            $currentStatus = $ticket->status->value;

            TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'type' => 'internal_check',
                'comment' => 'Hasil Pengecekan Mandiri PIC ['.strtoupper($result)."]: {$notes}",
                'is_internal' => true,
            ]);

            $ticket->histories()->create([
                'actor_id' => $user->id,
                'actor_role' => $user->role?->key ?? 'pic_it_support',
                'action' => 'internal_checked',
                'from_status' => $currentStatus,
                'to_status' => $currentStatus,
                'notes' => "Hasil Pengecekan Mandiri: {$result}. Catatan: {$notes}",
                'created_at' => $now,
            ]);
        });

        return ApiResponse::success($request, 'Hasil pengecekan mandiri berhasil dicatat.', [
            'result' => $result,
            'notes' => $notes,
            'ticket' => (new TicketResource($ticket->fresh(self::RELATIONS)))->resolve($request),
        ]);
    }

    public function submitForApproval(PicActionRequest $request, Ticket $ticket, TicketNotificationService $notificationService): JsonResponse
    {
        $user = $request->user();

        // Must be Primary PIC or Supervisor acting as Primary
        $this->ensurePrimaryPicOrSupervisor($ticket, $user);

        if (in_array($ticket->status, [TicketStatus::NeedInfo, TicketStatus::WaitingExternal, TicketStatus::Done, TicketStatus::Closed, TicketStatus::Rejected, TicketStatus::Cancelled], true)) {
            abort(409, 'Tiket sedang dalam kondisi menunggu informasi/eksternal atau sudah selesai.');
        }

        $validated = $request->validated();

        DB::transaction(function () use ($ticket, $user, $validated, $notificationService): void {
            $now = Carbon::now();
            $fromStatus = $ticket->status->value;

            // Save internal result note
            $internalNotes = $validated['internal_notes'] ?? null;
            TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'type' => 'approval_submission',
                'comment' => 'Pengajuan Pemeriksaan Akhir Supervisor: '.$validated['result_summary'].($internalNotes ? "\nCatatan: ".$internalNotes : ''),
                'is_internal' => true,
            ]);

            // Save public summary for Requester if provided
            $reqSummary = $validated['requester_summary'] ?? null;
            if (! empty($reqSummary)) {
                TicketComment::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'type' => 'requester_summary',
                    'comment' => 'Ringkasan Hasil Pekerjaan: '.$reqSummary,
                    'is_internal' => false,
                ]);
            }

            $ticket->fill([
                'status' => TicketStatus::PendingApproval,
                'development_completed_at' => $now,
                'progress_percentage' => 100,
            ])->save();

            $ticket->histories()->create([
                'actor_id' => $user->id,
                'actor_role' => $user->role?->key ?? 'pic_it_support',
                'action' => 'submitted_for_approval',
                'from_status' => $fromStatus,
                'to_status' => TicketStatus::PendingApproval->value,
                'notes' => 'PIC mengajukan tiket untuk pemeriksaan akhir Supervisor IT.',
                'created_at' => $now,
            ]);

            // Dispatch notification to Supervisor IT
            $notificationService->dispatch(
                $ticket,
                'supervisor_validation_required',
                'info',
                'Pemeriksaan Akhir Tiket #'.$ticket->ticket_number,
                'Primary PIC telah menyelesaikan pengerjaan dan mengajukan tiket untuk pemeriksaan akhir Supervisor.',
                "/supervisor-it/tickets/{$ticket->id}",
                $user->id
            );
        });

        return ApiResponse::success($request, 'Tiket berhasil dikirim untuk pemeriksaan akhir Supervisor IT.', (new TicketResource($ticket->fresh(self::RELATIONS)))->resolve($request));
    }

    public function requestAssistance(PicActionRequest $request, Ticket $ticket, TicketNotificationService $notificationService): JsonResponse
    {
        $user = $request->user();
        $this->ensureActiveAssignment($ticket, $user);

        $validated = $request->validated();
        $reason = $validated['reason'];
        $expertise = $validated['required_expertise'] ?? 'Umum';

        DB::transaction(function () use ($ticket, $user, $reason, $expertise, $notificationService): void {
            $now = Carbon::now();
            $currentStatus = $ticket->status->value;

            TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'type' => 'assistance_request',
                'comment' => "Permintaan Bantuan PIC [Keahlian: {$expertise}]: {$reason}",
                'is_internal' => true,
            ]);

            $ticket->histories()->create([
                'actor_id' => $user->id,
                'actor_role' => $user->role?->key ?? 'pic_it_support',
                'action' => 'assistance_requested',
                'from_status' => $currentStatus,
                'to_status' => $currentStatus,
                'notes' => "PIC mengajukan permintaan bantuan ke Supervisor: {$reason}",
                'created_at' => $now,
            ]);

            $notificationService->dispatch(
                $ticket,
                'supervisor_validation_required',
                'warning',
                'Permintaan Bantuan PIC Tiket #'.$ticket->ticket_number,
                "PIC {$user->name} mengajukan permintaan bantuan pendamping: {$reason}",
                "/supervisor-it/tickets/{$ticket->id}",
                $user->id
            );
        });

        return ApiResponse::success($request, 'Permintaan bantuan berhasil dikirim kepada Supervisor IT.', (new TicketResource($ticket->fresh(self::RELATIONS)))->resolve($request));
    }

    public function requestTransfer(PicActionRequest $request, Ticket $ticket, TicketNotificationService $notificationService): JsonResponse
    {
        $user = $request->user();
        $this->ensureActiveAssignment($ticket, $user);

        $validated = $request->validated();
        $reason = $validated['reason'];

        DB::transaction(function () use ($ticket, $user, $reason, $notificationService): void {
            $now = Carbon::now();
            $currentStatus = $ticket->status->value;

            TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'type' => 'transfer_request',
                'comment' => "Permintaan Pengalihan Tiket: {$reason}",
                'is_internal' => true,
            ]);

            $ticket->histories()->create([
                'actor_id' => $user->id,
                'actor_role' => $user->role?->key ?? 'pic_it_support',
                'action' => 'transfer_requested',
                'from_status' => $currentStatus,
                'to_status' => $currentStatus,
                'notes' => "PIC mengajukan pengalihan tiket ke Supervisor: {$reason}",
                'created_at' => $now,
            ]);

            $notificationService->dispatch(
                $ticket,
                'supervisor_validation_required',
                'warning',
                'Permintaan Pengalihan Tiket #'.$ticket->ticket_number,
                "PIC {$user->name} mengajukan pengalihan tiket: {$reason}",
                "/supervisor-it/tickets/{$ticket->id}",
                $user->id
            );
        });

        return ApiResponse::success($request, 'Permintaan pengalihan tiket berhasil dikirim kepada Supervisor IT.', (new TicketResource($ticket->fresh(self::RELATIONS)))->resolve($request));
    }

    // Helper assertions
    /**
     * @return list<string>
     */
    private function workspaceAllowedActions(Ticket $ticket, ?string $assignmentRole, bool $isSupervisor): array
    {
        if (in_array($ticket->status, [TicketStatus::Done, TicketStatus::Closed, TicketStatus::Rejected, TicketStatus::Cancelled], true)) {
            return [];
        }

        $isPrimary = $assignmentRole === 'primary' || $isSupervisor;
        $actions = [
            'add_work_note',
            'update_progress',
            'upload_attachment',
            'mark_waiting_external',
            'internal_check',
            'request_assistance',
            'request_transfer',
        ];

        if ($isPrimary) {
            $actions[] = 'request_info';
        }
        if ($isPrimary && in_array($ticket->status, [TicketStatus::Assigned, TicketStatus::UnderAnalysis], true)) {
            $actions[] = 'start';
        }
        if (in_array($ticket->status, [TicketStatus::NeedInfo, TicketStatus::WaitingExternal, TicketStatus::OnHold], true)) {
            $actions[] = 'resume';
        }
        if ($isPrimary && ! in_array($ticket->status, [TicketStatus::NeedInfo, TicketStatus::WaitingExternal], true)) {
            $actions[] = 'submit_for_approval';
        }

        return $actions;
    }

    private function ensureActiveAssignment(Ticket $ticket, $user): void
    {
        $hasAssignment = $ticket->assignments()
            ->where('assigned_to', $user->id)
            ->where('is_current', true)
            ->exists();

        if (! $hasAssignment && ! $user->hasRole('supervisor_it')) {
            abort(403, 'Anda tidak memiliki assignment aktif pada tiket ini.');
        }
    }

    private function ensurePrimaryPicOrSupervisor(Ticket $ticket, $user): void
    {
        $isPrimary = $ticket->assignments()
            ->where('assigned_to', $user->id)
            ->where('is_current', true)
            ->where('assignment_type', 'primary')
            ->exists();

        if (! $isPrimary && ! $user->hasRole('supervisor_it')) {
            abort(403, 'Hanya Primary PIC yang dapat melakukan tindakan ini.');
        }
    }

    // Legacy analysis/solution plan methods preservation
    public function startAnalysis(TicketListRequest $request, Ticket $ticket, TicketAnalysisService $service): JsonResponse
    {
        Gate::authorize('analyze', $ticket);
        $ticket = $service->start($ticket, $request->user())->load(self::RELATIONS);

        return ApiResponse::success($request, 'Analysis started', (new TicketResource($ticket))->resolve($request));
    }

    public function analysis(TicketListRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('analyze', $ticket);
        $items = $ticket->analyses()->with('analyst')->get();

        return ApiResponse::success($request, 'Ticket analysis retrieved', [
            'current' => $ticket->current_analysis_id ? (new TicketAnalysisResource($items->firstWhere('id', $ticket->current_analysis_id)))->resolve($request) : null,
            'versions' => TicketAnalysisResource::collection($items),
        ]);
    }

    public function storeAnalysis(SaveTicketAnalysisRequest $request, Ticket $ticket, TicketAnalysisService $service): JsonResponse
    {
        Gate::authorize('analyze', $ticket);

        return ApiResponse::success($request, 'Analysis draft saved', (new TicketAnalysisResource($service->create($ticket, $request->user(), $request->validated())))->resolve($request), 201);
    }

    public function updateAnalysis(SaveTicketAnalysisRequest $request, Ticket $ticket, TicketAnalysis $analysis, TicketAnalysisService $service): JsonResponse
    {
        Gate::authorize('analyze', $ticket);

        return ApiResponse::success($request, 'Analysis draft saved', (new TicketAnalysisResource($service->update($ticket, $analysis, $request->user(), $request->validated())))->resolve($request));
    }

    public function completeAnalysis(TicketListRequest $request, Ticket $ticket, TicketAnalysis $analysis, TicketAnalysisService $service): JsonResponse
    {
        Gate::authorize('analyze', $ticket);

        return ApiResponse::success($request, 'Analysis completed', (new TicketAnalysisResource($service->complete($ticket, $analysis, $request->user())))->resolve($request));
    }

    public function solutionPlan(TicketListRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('analyze', $ticket);
        $items = $ticket->solutionPlans()->with(['creator', 'reviewer'])->get();

        return ApiResponse::success($request, 'Solution plan retrieved', [
            'current' => $ticket->current_solution_plan_id ? (new TicketSolutionPlanResource($items->firstWhere('id', $ticket->current_solution_plan_id)))->resolve($request) : null,
            'versions' => TicketSolutionPlanResource::collection($items),
        ]);
    }

    public function storeSolutionPlan(SaveTicketSolutionPlanRequest $request, Ticket $ticket, TicketSolutionPlanService $service): JsonResponse
    {
        Gate::authorize('analyze', $ticket);

        return ApiResponse::success($request, 'Solution plan draft saved', (new TicketSolutionPlanResource($service->create($ticket, $request->user(), $request->validated())))->resolve($request), 201);
    }

    public function updateSolutionPlan(SaveTicketSolutionPlanRequest $request, Ticket $ticket, TicketSolutionPlan $plan, TicketSolutionPlanService $service): JsonResponse
    {
        Gate::authorize('analyze', $ticket);

        return ApiResponse::success($request, 'Solution plan draft saved', (new TicketSolutionPlanResource($service->update($ticket, $plan, $request->user(), $request->validated())))->resolve($request));
    }

    public function submitSolutionPlan(TicketListRequest $request, Ticket $ticket, TicketSolutionPlan $plan, TicketSolutionPlanService $service): JsonResponse
    {
        Gate::authorize('analyze', $ticket);

        return ApiResponse::success($request, 'Solution plan submitted', (new TicketSolutionPlanResource($service->submit($ticket, $plan, $request->user())))->resolve($request));
    }
}
