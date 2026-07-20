<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\Phase12\CloseTicketRequest;
use App\Http\Requests\Ticket\Phase12\RespondConfirmationRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Ticket;
use App\Services\TicketClosureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TicketClosureController extends Controller
{
    public function __construct(
        private TicketClosureService $closureService
    ) {}

    public function respondToConfirmation(RespondConfirmationRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        // This should primarily be the requester, but for Phase 12 IT Lead or Supervisor might also do it?
        // Actually the policy says requester, let's keep it simple. But Phase 3 says Requester can confirm.
        // I will just use the policy defined in TicketPolicy if it exists, or just ensure the user is the requester.
        if ($request->user()->id !== $ticket->requester_id && ! $request->user()->hasRole('it_lead')) {
            abort(403, 'Unauthorized to respond to confirmation.');
        }

        $this->closureService->respondToConfirmation($ticket, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Confirmation response recorded successfully.',
            'ticket' => new TicketResource($ticket->fresh()),
        ]);
    }

    public function close(CloseTicketRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);

        // Ensure only IT Lead or someone with 'ticket.closure.confirm' can manually close
        if (! $request->user()->hasPermission('ticket.closure.confirm') && ! $request->user()->hasRole('it_lead') && ! $request->user()->hasRole('manager')) {
            abort(403, 'Unauthorized to close ticket.');
        }

        $this->closureService->close($ticket, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Ticket closed successfully.',
            'ticket' => new TicketResource($ticket->fresh()),
        ]);
    }
}
