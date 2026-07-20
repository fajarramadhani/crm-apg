<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\Phase12\CompleteMonitoringRequest;
use App\Http\Requests\Ticket\Phase12\RecordMonitoringCheckRequest;
use App\Http\Requests\Ticket\Phase12\RecordPostReleaseIncidentRequest;
use App\Http\Requests\Ticket\Phase12\StartMonitoringRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Ticket;
use App\Services\TicketMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TicketMonitoringController extends Controller
{
    public function __construct(
        private TicketMonitoringService $monitoringService
    ) {}

    public function start(StartMonitoringRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        Gate::authorize('manageReleasePreparation', $ticket); // Ensure the actor is PIC/IT Lead for this ticket

        $deployment = $ticket->deployments()->latest('version')->firstOrFail();

        $session = $this->monitoringService->start($ticket, $deployment, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Monitoring started successfully.',
            'session' => $session,
            'ticket' => new TicketResource($ticket->fresh()),
        ], 201);
    }

    public function recordCheck(RecordMonitoringCheckRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        Gate::authorize('manageReleasePreparation', $ticket);

        $deployment = $ticket->deployments()->latest('version')->firstOrFail();
        $session = $deployment->monitoringSessions()->latest('cycle_number')->firstOrFail();

        $this->monitoringService->recordCheck($session, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Monitoring check recorded successfully.',
            'ticket' => new TicketResource($ticket->fresh()),
        ]);
    }

    public function recordIncident(RecordPostReleaseIncidentRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        Gate::authorize('manageReleasePreparation', $ticket);

        $deployment = $ticket->deployments()->latest('version')->firstOrFail();
        $session = $deployment->monitoringSessions()->latest('cycle_number')->firstOrFail();

        $this->monitoringService->recordIncident($session, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Post-release incident recorded successfully.',
            'ticket' => new TicketResource($ticket->fresh()),
        ]);
    }

    public function complete(CompleteMonitoringRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        Gate::authorize('manageReleasePreparation', $ticket);

        $deployment = $ticket->deployments()->latest('version')->firstOrFail();
        $session = $deployment->monitoringSessions()->latest('cycle_number')->firstOrFail();

        $this->monitoringService->complete($session, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Monitoring completed successfully.',
            'ticket' => new TicketResource($ticket->fresh()),
        ]);
    }
}
