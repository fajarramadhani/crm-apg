<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignTicketRequest;
use App\Http\Requests\Api\V1\RequestPlanRevisionRequest;
use App\Http\Requests\Api\V1\TicketListRequest;
use App\Http\Resources\Api\V1\TicketAnalysisResource;
use App\Http\Resources\Api\V1\TicketResource;
use App\Http\Resources\Api\V1\TicketSolutionPlanResource;
use App\Models\Ticket;
use App\Models\TicketSolutionPlan;
use App\Models\User;
use App\Services\TicketAssignmentService;
use App\Services\TicketSolutionPlanService;
use App\Services\TicketTransitionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class ItLeadTicketController extends Controller
{
    private const RELATIONS = ['requester.division', 'division', 'currentDivision', 'branch', 'application', 'applicationModule', 'category', 'requestedPriority', 'finalPriority', 'slaPolicy', 'workingCalendar', 'currentAssignee', 'assignments', 'attachments'];

    public function queue(TicketListRequest $request): JsonResponse
    {
        $query = Ticket::query()->whereIn('status', [TicketStatus::Validated, TicketStatus::Triage])->with(self::RELATIONS);
        $query->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('ticket_number', 'like', '%'.$request->string('search').'%')->orWhere('title', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('category'), fn ($q) => $q->where('ticket_category_id', $request->integer('category')))
            ->when($request->filled('application'), fn ($q) => $q->where('application_id', $request->integer('application')))
            ->when($request->filled('requested_priority'), fn ($q) => $q->where('requested_priority_id', $request->integer('requested_priority')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('submitted_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('submitted_at', '<=', $request->date('date_to')));
        $page = $query->oldest('submitted_at')->paginate($request->integer('per_page', 20));

        return ApiResponse::success($request, 'Triage queue retrieved', TicketResource::collection($page->items())->resolve($request), meta: ['pagination' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]]);
    }

    public function show(TicketListRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('triage', $ticket);
        $ticket->load([...self::RELATIONS, 'histories.actor', 'comments.user']);

        return ApiResponse::success($request, 'Triage ticket retrieved', (new TicketResource($ticket))->resolve($request));
    }

    public function startTriage(TicketListRequest $request, Ticket $ticket, TicketTransitionService $service): JsonResponse
    {
        Gate::authorize('triage', $ticket);
        $ticket = $service->startTriage($ticket, $request->user())->load(self::RELATIONS);

        return ApiResponse::success($request, 'Triage started', (new TicketResource($ticket))->resolve($request));
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket, TicketAssignmentService $service): JsonResponse
    {
        Gate::authorize('triage', $ticket);
        $ticket = $service->assign($ticket, $request->user(), $request->integer('final_priority_id'), $request->integer('pic_user_id'), $request->input('notes'))->load(self::RELATIONS);

        return ApiResponse::success($request, 'Ticket assigned', (new TicketResource($ticket))->resolve($request));
    }

    public function picOptions(TicketListRequest $request): JsonResponse
    {
        $pics = $this->pics()->get()->map(fn (User $pic) => $this->picData($pic))->all();

        return ApiResponse::success($request, 'PIC options retrieved', $pics);
    }

    public function picWorkloads(TicketListRequest $request): JsonResponse
    {
        return ApiResponse::success($request, 'PIC workloads retrieved', $this->pics()->get()->map(fn (User $pic) => $this->picData($pic))->all());
    }

    public function planReviewQueue(TicketListRequest $request): JsonResponse
    {
        $query = Ticket::query()->where('status', TicketStatus::PlanReview)->with([...self::RELATIONS, 'currentSolutionPlan.creator']);
        $query->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('ticket_number', 'like', '%'.$request->string('search').'%')->orWhere('title', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('application'), fn ($q) => $q->where('application_id', $request->integer('application')))
            ->when($request->filled('priority'), fn ($q) => $q->where('final_priority_id', $request->integer('priority')))
            ->when($request->filled('requester'), fn ($q) => $q->where('requester_id', $request->integer('requester')));
        $page = $query->oldest('plan_submitted_at')->paginate($request->integer('per_page', 20));

        return ApiResponse::success($request, 'Plan review queue retrieved', TicketResource::collection($page->items())->resolve($request), meta: ['pagination' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]]);
    }

    public function solutionPlan(TicketListRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('reviewPlan', $ticket);
        $ticket->load([...self::RELATIONS, 'histories.actor', 'comments.user', 'currentAnalysis.analyst', 'currentSolutionPlan.creator', 'currentSolutionPlan.reviewer']);

        return ApiResponse::success($request, 'Plan review detail retrieved', ['ticket' => (new TicketResource($ticket))->resolve($request), 'analysis' => $ticket->currentAnalysis ? (new TicketAnalysisResource($ticket->currentAnalysis))->resolve($request) : null, 'solution_plan' => $ticket->currentSolutionPlan ? (new TicketSolutionPlanResource($ticket->currentSolutionPlan))->resolve($request) : null]);
    }

    public function approvePlan(TicketListRequest $request, Ticket $ticket, TicketSolutionPlan $plan, TicketSolutionPlanService $service): JsonResponse
    {
        Gate::authorize('reviewPlan', $ticket);

        return ApiResponse::success($request, 'Solution plan approved', (new TicketSolutionPlanResource($service->approve($ticket, $plan, $request->user())))->resolve($request));
    }

    public function requestPlanRevision(RequestPlanRevisionRequest $request, Ticket $ticket, TicketSolutionPlan $plan, TicketSolutionPlanService $service): JsonResponse
    {
        Gate::authorize('reviewPlan', $ticket);

        return ApiResponse::success($request, 'Solution plan revision requested', (new TicketSolutionPlanResource($service->requestRevision($ticket, $plan, $request->user(), $request->string('review_notes')->toString())))->resolve($request));
    }

    private function pics()
    {
        return User::query()->with(['role', 'division'])->where('is_active', true)->whereHas('role', fn ($q) => $q->where('key', 'pic')->where('is_active', true))
            ->withCount(['ticketAssignments as active_assignment_count' => fn ($q) => $q->where('is_current', true), 'ticketAssignments as critical_count' => fn ($q) => $q->where('is_current', true)->whereHas('ticket.finalPriority', fn ($q) => $q->where('key', 'critical')), 'ticketAssignments as high_count' => fn ($q) => $q->where('is_current', true)->whereHas('ticket.finalPriority', fn ($q) => $q->where('key', 'high'))])->orderBy('name');
    }

    private function picData(User $pic): array
    {
        $count = (int) $pic->active_assignment_count;

        return ['id' => $pic->id, 'name' => $pic->name, 'email' => $pic->email, 'division' => $pic->division ? ['id' => $pic->division->id, 'name' => $pic->division->name] : null, 'active_assignment_count' => $count, 'critical_count' => (int) $pic->critical_count, 'high_count' => (int) $pic->high_count, 'workload_indicator' => $count >= 8 ? 'high' : ($count >= 4 ? 'medium' : 'low')];
    }
}
