<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\Phase12\CompleteDeploymentRequest;
use App\Http\Requests\Ticket\Phase12\CompleteRollbackRequest;
use App\Http\Requests\Ticket\Phase12\FailDeploymentRequest;
use App\Http\Requests\Ticket\Phase12\ManageDeploymentStepRequest;
use App\Http\Requests\Ticket\Phase12\ScheduleDeploymentRequest;
use App\Http\Requests\Ticket\Phase12\StartDeploymentRequest;
use App\Http\Requests\Ticket\Phase12\StartRollbackRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Ticket;
use App\Services\TicketDeploymentExecutionService;
use App\Services\TicketDeploymentSchedulingService;
use App\Services\TicketRollbackService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketDeploymentController extends Controller
{
    public function __construct(
        private TicketDeploymentSchedulingService $schedulingService,
        private TicketDeploymentExecutionService $executionService,
        private TicketRollbackService $rollbackService
    ) {}

    public function queue(Request $request): JsonResponse
    {
        $items = Ticket::query()
            ->whereIn('status', [
                'release_ready',
                'deployment_scheduled',
                'deployment_in_progress',
                'deployed',
                'monitoring',
                'post_release_issue',
            ])
            ->with(['requester.division', 'division', 'application', 'category', 'currentAssignee'])
            ->latest('updated_at')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            $request,
            'Deployment queue retrieved',
            TicketResource::collection($items->items())->resolve($request),
            meta: [
                'pagination' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                    'last_page' => $items->lastPage(),
                ],
            ]
        );
    }

    public function schedule(ScheduleDeploymentRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        Gate::authorize('manageReleasePreparation', $ticket); // Based on Phase 11 permissions, IT Lead or PIC manages deployment preparation

        $deployment = $this->schedulingService->schedule($ticket, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Deployment scheduled successfully.',
            'deployment' => $deployment,
            'ticket' => new TicketResource($ticket->fresh()),
        ], 201);
    }

    public function start(StartDeploymentRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        Gate::authorize('manageReleasePreparation', $ticket);

        $deployment = $ticket->deployments()->latest('version')->firstOrFail();

        $this->executionService->start($deployment, $request->user(), $request->validated('notes'));

        return response()->json([
            'message' => 'Deployment started successfully.',
            'ticket' => new TicketResource($ticket->fresh()),
        ]);
    }

    public function manageStep(ManageDeploymentStepRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        Gate::authorize('manageReleasePreparation', $ticket);

        $deployment = $ticket->deployments()->latest('version')->firstOrFail();

        $this->executionService->manageStep($deployment, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Deployment step updated successfully.',
            'ticket' => new TicketResource($ticket->fresh()),
        ]);
    }

    public function complete(CompleteDeploymentRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        Gate::authorize('manageReleasePreparation', $ticket);

        $deployment = $ticket->deployments()->latest('version')->firstOrFail();

        $this->executionService->complete($deployment, $request->user(), $request->validated('summary'));

        return response()->json([
            'message' => 'Deployment completed successfully.',
            'ticket' => new TicketResource($ticket->fresh()),
        ]);
    }

    public function fail(FailDeploymentRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        Gate::authorize('manageReleasePreparation', $ticket);

        $deployment = $ticket->deployments()->latest('version')->firstOrFail();

        $this->executionService->fail($deployment, $request->user(), $request->validated('reason'));

        return response()->json([
            'message' => 'Deployment marked as failed.',
            'ticket' => new TicketResource($ticket->fresh()),
        ]);
    }

    public function startRollback(StartRollbackRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        Gate::authorize('manageReleasePreparation', $ticket);

        $deployment = $ticket->deployments()->latest('version')->firstOrFail();

        $this->rollbackService->start($ticket, $deployment, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Rollback started successfully.',
            'ticket' => new TicketResource($ticket->fresh()),
        ]);
    }

    public function completeRollback(CompleteRollbackRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        Gate::authorize('manageReleasePreparation', $ticket);

        $deployment = $ticket->deployments()->latest('version')->firstOrFail();
        $rollback = $deployment->rollbacks()->latest('version')->firstOrFail();

        $this->rollbackService->complete($rollback, $request->user(), $request->validated('summary'));

        return response()->json([
            'message' => 'Rollback completed successfully.',
            'ticket' => new TicketResource($ticket->fresh()),
        ]);
    }
}
