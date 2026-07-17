<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignUatRequest;
use App\Http\Requests\Api\V1\TicketListRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Ticket;
use App\Services\TicketUatAssignmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ItLeadUatController extends Controller
{
    private const RELATIONS = ['requester.division', 'division', 'currentDivision', 'branch', 'application', 'applicationModule', 'category', 'requestedPriority', 'finalPriority', 'slaPolicy', 'workingCalendar', 'currentAssignee', 'assignments', 'attachments'];

    public function queue(TicketListRequest $request): JsonResponse
    {
        $query = Ticket::query()->whereIn('status', [TicketStatus::ReadyForUat, TicketStatus::UatAssignment])->with(self::RELATIONS);

        $query->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('ticket_number', 'like', '%'.$request->string('search').'%')->orWhere('title', 'like', '%'.$request->string('search').'%')));

        $page = $query->oldest('ready_for_uat_at')->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            $request,
            'UAT assignment queue retrieved',
            TicketResource::collection($page->items())->resolve($request),
            meta: [
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                    'last_page' => $page->lastPage(),
                ],
            ]
        );
    }

    public function assignUat(AssignUatRequest $request, Ticket $ticket, TicketUatAssignmentService $service): JsonResponse
    {
        Gate::authorize('assignUat', $ticket);

        $ticket = $service->assign($ticket, $request->user(), $request->integer('requester_user_id'), $request->string('notes'));

        return ApiResponse::success($request, 'UAT assignee successfully assigned', (new TicketResource($ticket))->resolve($request));
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        $ticket->load(self::RELATIONS);

        return ApiResponse::success($request, 'UAT ticket details retrieved', (new TicketResource($ticket))->resolve($request));
    }
}
