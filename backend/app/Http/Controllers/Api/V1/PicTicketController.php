<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TicketListRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Ticket;
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
}
