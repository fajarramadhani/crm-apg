<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ResolveQaDefectRequest;
use App\Http\Requests\Api\V1\SubmitQaRetestRequest;
use App\Http\Resources\Api\V1\TicketQaDefectResource;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Ticket;
use App\Models\TicketQaDefect;
use App\Services\TicketQaDefectService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class PicReworkController extends Controller
{
    private const RELATIONS = ['requester.division', 'division', 'currentDivision', 'branch', 'application', 'applicationModule', 'category', 'requestedPriority', 'finalPriority', 'slaPolicy', 'workingCalendar', 'currentAssignee', 'assignments', 'attachments'];

    public function defects(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('develop', $ticket);

        $defects = $ticket->qaDefects()->with('reporter', 'assignee', 'resolver')->get();

        return ApiResponse::success($request, 'QA defects retrieved for rework', TicketQaDefectResource::collection($defects)->resolve($request));
    }

    public function startDefect(Request $request, Ticket $ticket, TicketQaDefect $defect, TicketQaDefectService $service): JsonResponse
    {
        Gate::authorize('develop', $ticket);

        // Nested resource validation
        if ($defect->ticket_id !== $ticket->id) {
            abort(404, 'Defect does not belong to this ticket.');
        }

        $defect = $service->startDefect($ticket, $defect, $request->user());

        return ApiResponse::success($request, 'PIC started working on defect', (new TicketQaDefectResource($defect->load('histories')))->resolve($request));
    }

    public function resolveDefect(ResolveQaDefectRequest $request, Ticket $ticket, TicketQaDefect $defect, TicketQaDefectService $service): JsonResponse
    {
        Gate::authorize('develop', $ticket);

        // Nested resource validation
        if ($defect->ticket_id !== $ticket->id) {
            abort(404, 'Defect does not belong to this ticket.');
        }

        $defect = $service->resolveDefect($ticket, $defect, $request->user(), $request->string('resolution_notes'));

        return ApiResponse::success($request, 'Defect resolved successfully', (new TicketQaDefectResource($defect->load('histories')))->resolve($request));
    }

    public function submitRetest(SubmitQaRetestRequest $request, Ticket $ticket, TicketQaDefectService $service): JsonResponse
    {
        Gate::authorize('develop', $ticket);

        $ticket = $service->submitRetest($ticket, $request->user());

        return ApiResponse::success($request, 'Ticket successfully submitted for QA Retest', (new TicketResource($ticket->load(self::RELATIONS)))->resolve($request));
    }
}
