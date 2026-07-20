<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApprovalDecisionRequest;
use App\Http\Requests\Api\V1\ChecklistDecisionRequest;
use App\Http\Requests\Api\V1\ReleasePlanReviewRequest;
use App\Http\Requests\Api\V1\RequestReleaseApprovalRequest;
use App\Http\Requests\Api\V1\SaveReleasePlanRequest;
use App\Http\Requests\Api\V1\SaveRollbackPlanRequest;
use App\Http\Resources\Api\V1\TicketApprovalRequestResource;
use App\Http\Resources\Api\V1\TicketReleaseChecklistItemResource;
use App\Http\Resources\Api\V1\TicketReleasePlanResource;
use App\Http\Resources\Api\V1\TicketResource;
use App\Http\Resources\Api\V1\TicketRollbackPlanResource;
use App\Models\Ticket;
use App\Models\TicketApprovalRequest;
use App\Models\TicketReleaseChecklistItem;
use App\Models\TicketReleasePlan;
use App\Models\TicketRollbackPlan;
use App\Services\TicketApprovalDecisionService;
use App\Services\TicketApprovalRequestService;
use App\Services\TicketReleasePreparationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ItLeadReleaseController extends Controller
{
    private const RELATIONS = ['requester.division', 'division', 'currentDivision', 'application', 'category', 'finalPriority', 'currentAssignee', 'releaseOwner', 'approvalRequests.steps.approver', 'releasePlans.releaseOwner'];

    public function approvalQueue(Request $request): JsonResponse
    {
        $items = Ticket::query()->where('status', 'uat_approved')->with(self::RELATIONS)->latest('uat_approved_at')->paginate($request->integer('per_page', 20));

        return ApiResponse::success($request, 'Release approval request queue retrieved', TicketResource::collection($items->items())->resolve($request), meta: ['pagination' => ['current_page' => $items->currentPage(), 'per_page' => $items->perPage(), 'total' => $items->total(), 'last_page' => $items->lastPage()]]);
    }

    public function requestApproval(RequestReleaseApprovalRequest $request, Ticket $ticket, TicketApprovalRequestService $service): JsonResponse
    {
        Gate::authorize('requestReleaseApproval', $ticket);
        $approval = $service->request($ticket, $request->user(), $request->validated());

        return ApiResponse::success($request, 'Release approval requested', (new TicketApprovalRequestResource($approval))->resolve($request), 201);
    }

    public function technicalQueue(Request $request): JsonResponse
    {
        $items = TicketApprovalRequest::query()->where('status', 'pending')->whereHas('steps', fn ($q) => $q->where('step_type', 'technical_readiness')->where('approver_id', $request->user()->id)->where('status', 'pending'))->with(['ticket', 'steps.approver'])->latest('requested_at')->get();

        return ApiResponse::success($request, 'Technical approval queue retrieved', TicketApprovalRequestResource::collection($items)->resolve($request));
    }

    public function technicalDecision(ApprovalDecisionRequest $request, Ticket $ticket, string $decision, TicketApprovalDecisionService $service): JsonResponse
    {
        Gate::authorize('technicalApprove', $ticket);
        $approval = $ticket->approvalRequests()->where('status', 'pending')->latest('cycle_number')->firstOrFail();
        $approval = $service->decide($approval, 'technical_readiness', $request->user(), $decision === 'approve' ? 'approved' : 'rejected', $request->string('notes')->toString(), $request->integer('expected_version'));

        return ApiResponse::success($request, 'Technical readiness decision recorded', (new TicketApprovalRequestResource($approval))->resolve($request));
    }

    public function preparation(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('viewReleasePreparation', $ticket);
        $ticket->load(self::RELATIONS);

        return ApiResponse::success($request, 'Release preparation retrieved', ['ticket' => (new TicketResource($ticket))->resolve($request), 'approval' => ($approval = $ticket->approvalRequests()->latest('cycle_number')->with(['steps.approver', 'histories.actor'])->first()) ? (new TicketApprovalRequestResource($approval))->resolve($request) : null, 'release_plan' => ($plan = $ticket->releasePlans()->latest('version')->with(['releaseOwner', 'checklistItems.completedBy'])->first()) ? (new TicketReleasePlanResource($plan))->resolve($request) : null, 'rollback_plan' => ($rollback = $ticket->rollbackPlans()->latest('version')->with('responsibleUser')->first()) ? (new TicketRollbackPlanResource($rollback))->resolve($request) : null]);
    }

    public function storePlan(SaveReleasePlanRequest $request, Ticket $ticket, TicketReleasePreparationService $service): JsonResponse
    {
        Gate::authorize('manageReleasePreparation', $ticket);
        $plan = $service->createPlan($ticket, $request->user(), $request->validated());

        return ApiResponse::success($request, 'Release plan created', (new TicketReleasePlanResource($plan))->resolve($request), 201);
    }

    public function updatePlan(SaveReleasePlanRequest $request, Ticket $ticket, TicketReleasePlan $plan, TicketReleasePreparationService $service): JsonResponse
    {
        Gate::authorize('manageReleasePreparation', $ticket);
        $plan = $service->updatePlan($plan, $ticket, $request->user(), $request->validated(), $request->integer('expected_lock_version'));

        return ApiResponse::success($request, 'Release plan updated', (new TicketReleasePlanResource($plan))->resolve($request));
    }

    public function submitPlan(Request $request, Ticket $ticket, TicketReleasePlan $plan, TicketReleasePreparationService $service): JsonResponse
    {
        Gate::authorize('manageReleasePreparation', $ticket);
        $plan = $service->submitPlan($plan, $ticket, $request->user());

        return ApiResponse::success($request, 'Release plan submitted', (new TicketReleasePlanResource($plan))->resolve($request));
    }

    public function reviewPlan(ReleasePlanReviewRequest $request, Ticket $ticket, TicketReleasePlan $plan, string $decision, TicketReleasePreparationService $service): JsonResponse
    {
        Gate::authorize('reviewReleasePlan', $ticket);
        $plan = $service->reviewPlan($plan, $ticket, $request->user(), $decision === 'approve' ? 'approved' : 'revision_required');

        return ApiResponse::success($request, 'Release plan review recorded', (new TicketReleasePlanResource($plan))->resolve($request));
    }

    public function storeRollback(SaveRollbackPlanRequest $request, Ticket $ticket, TicketReleasePreparationService $service): JsonResponse
    {
        Gate::authorize('manageReleasePreparation', $ticket);
        $plan = $service->createRollback($ticket, $request->user(), $request->validated());

        return ApiResponse::success($request, 'Rollback plan created', (new TicketRollbackPlanResource($plan))->resolve($request), 201);
    }

    public function reviewRollback(ReleasePlanReviewRequest $request, Ticket $ticket, TicketRollbackPlan $plan, string $decision, TicketReleasePreparationService $service): JsonResponse
    {
        Gate::authorize('reviewReleasePlan', $ticket);
        $plan = $service->reviewRollback($plan, $ticket, $request->user(), $decision === 'approve' ? 'approved' : 'revision_required');

        return ApiResponse::success($request, 'Rollback plan review recorded', (new TicketRollbackPlanResource($plan))->resolve($request));
    }

    public function submitRollback(Request $request, Ticket $ticket, TicketRollbackPlan $plan, TicketReleasePreparationService $service): JsonResponse
    {
        Gate::authorize('manageReleasePreparation', $ticket);
        $plan = $service->submitRollback($plan, $ticket, $request->user());

        return ApiResponse::success($request, 'Rollback plan submitted', (new TicketRollbackPlanResource($plan))->resolve($request));
    }

    public function checklist(Request $request, Ticket $ticket, TicketReleasePreparationService $service): JsonResponse
    {
        Gate::authorize('viewReleasePreparation', $ticket);

        return ApiResponse::success($request, 'Release checklist retrieved', TicketReleaseChecklistItemResource::collection($service->checklist($ticket, $request->user()))->resolve($request));
    }

    public function checklistDecision(ChecklistDecisionRequest $request, Ticket $ticket, TicketReleaseChecklistItem $item, TicketReleasePreparationService $service): JsonResponse
    {
        Gate::authorize('manageReleasePreparation', $ticket);
        $item = $service->updateChecklist($item, $ticket, $request->user(), $request->string('status')->toString(), $request->string('notes')->toString(), $request->integer('expected_version'));

        return ApiResponse::success($request, 'Release checklist updated', (new TicketReleaseChecklistItemResource($item))->resolve($request));
    }

    public function confirmReady(Request $request, Ticket $ticket, TicketReleasePreparationService $service): JsonResponse
    {
        Gate::authorize('confirmReleaseReady', $ticket);
        $ticket = $service->confirmReady($ticket, $request->user());

        return ApiResponse::success($request, 'Ticket marked release ready', (new TicketResource($ticket->load(self::RELATIONS)))->resolve($request));
    }
}
