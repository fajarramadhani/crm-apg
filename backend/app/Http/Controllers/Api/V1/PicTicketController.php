<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveTicketAnalysisRequest;
use App\Http\Requests\Api\V1\SaveTicketSolutionPlanRequest;
use App\Http\Requests\Api\V1\TicketListRequest;
use App\Http\Resources\Api\V1\TicketAnalysisResource;
use App\Http\Resources\Api\V1\TicketResource;
use App\Http\Resources\Api\V1\TicketSolutionPlanResource;
use App\Models\Ticket;
use App\Models\TicketAnalysis;
use App\Models\TicketSolutionPlan;
use App\Services\TicketAnalysisService;
use App\Services\TicketSolutionPlanService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class PicTicketController extends Controller
{
    private const RELATIONS = ['requester', 'division', 'currentDivision', 'branch', 'application', 'applicationModule', 'category', 'requestedPriority', 'finalPriority', 'slaPolicy', 'workingCalendar', 'currentAssignee', 'attachments'];

    public function index(TicketListRequest $request): JsonResponse
    {
        $q = Ticket::query()->where('current_assignee_id', $request->user()->id)->whereHas('assignments', fn ($q) => $q->where('assigned_to', $request->user()->id)->where('is_current', true))->with(self::RELATIONS);
        $q->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))->when($request->filled('priority'), fn ($q) => $q->where('final_priority_id', $request->integer('priority')));
        $p = $q->latest('assigned_at')->paginate($request->integer('per_page', 20));

        return ApiResponse::success($request, 'PIC assignments retrieved', TicketResource::collection($p->items())->resolve($request), meta: ['pagination' => ['current_page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total(), 'last_page' => $p->lastPage()]]);
    }

    public function show(TicketListRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        abort_unless($ticket->assignments()->where('assigned_to', $request->user()->id)->where('is_current', true)->exists(), 403);
        $ticket->load([...self::RELATIONS, 'histories.actor', 'comments.user', 'assignments.assignee', 'assignments.assigner']);

        return ApiResponse::success($request, 'PIC ticket retrieved', (new TicketResource($ticket))->resolve($request));
    }

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

        return ApiResponse::success($request, 'Ticket analysis retrieved', ['current' => $ticket->current_analysis_id ? (new TicketAnalysisResource($items->firstWhere('id', $ticket->current_analysis_id)))->resolve($request) : null, 'versions' => TicketAnalysisResource::collection($items)->resolve($request)]);
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

        return ApiResponse::success($request, 'Solution plan retrieved', ['current' => $ticket->current_solution_plan_id ? (new TicketSolutionPlanResource($items->firstWhere('id', $ticket->current_solution_plan_id)))->resolve($request) : null, 'versions' => TicketSolutionPlanResource::collection($items)->resolve($request)]);
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
