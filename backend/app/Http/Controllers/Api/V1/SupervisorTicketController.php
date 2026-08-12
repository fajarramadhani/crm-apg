<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TicketActionRequest;
use App\Http\Requests\Api\V1\TicketListRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Division;
use App\Models\Ticket;
use App\Services\TicketTransitionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SupervisorTicketController extends Controller
{
    private const RELATIONS = ['requester', 'division', 'currentDivision', 'branch', 'application', 'applicationModule', 'category', 'requestedPriority', 'attachments'];

    public function queue(TicketListRequest $request): JsonResponse
    {
        $q = Ticket::query()->where('current_division_id', $request->user()->division_id)->where('status', TicketStatus::PendingValidation)->with(self::RELATIONS);
        $q->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('ticket_number', 'like', '%'.$request->string('search').'%')->orWhere('title', 'like', '%'.$request->string('search').'%')->orWhere('requester_name', 'like', '%'.$request->string('search').'%')))->when($request->filled('category'), fn ($q) => $q->where('ticket_category_id', $request->integer('category')))->when($request->filled('application'), fn ($q) => $q->where('application_id', $request->integer('application')))->when($request->filled('requester'), fn ($q) => $q->where('requester_id', $request->integer('requester')))->when($request->filled('submitted_from'), fn ($q) => $q->whereDate('submitted_at', '>=', $request->date('submitted_from')))->when($request->filled('submitted_to'), fn ($q) => $q->whereDate('submitted_at', '<=', $request->date('submitted_to')));
        $p = $q->oldest('submitted_at')->paginate($request->integer('per_page', 20));

        return ApiResponse::success($request, 'Validation queue retrieved', TicketResource::collection($p->items())->resolve($request), meta: ['pagination' => ['current_page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total(), 'last_page' => $p->lastPage()]]);
    }

    public function show(TicketListRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('supervise', $ticket);
        $ticket->load([...self::RELATIONS, 'histories.actor', 'comments.user']);

        return ApiResponse::success($request, 'Supervisor ticket retrieved', (new TicketResource($ticket))->resolve($request));
    }

    public function validate(TicketActionRequest $r, Ticket $ticket, TicketTransitionService $s): JsonResponse
    {
        Gate::authorize('supervise', $ticket);

        return $this->result($r, $s->validate($ticket, $r->user(), $r->input('notes')), 'Ticket validated');
    }

    public function requestRevision(TicketActionRequest $r, Ticket $ticket, TicketTransitionService $s): JsonResponse
    {
        Gate::authorize('supervise', $ticket);

        return $this->result($r, $s->requestRevision($ticket, $r->user(), $r->string('notes')), 'Revision requested');
    }

    public function reject(TicketActionRequest $r, Ticket $ticket, TicketTransitionService $s): JsonResponse
    {
        Gate::authorize('supervise', $ticket);

        return $this->result($r, $s->reject($ticket, $r->user(), $r->string('notes')), 'Ticket rejected');
    }

    public function transfer(TicketActionRequest $r, Ticket $ticket, TicketTransitionService $s): JsonResponse
    {
        Gate::authorize('supervise', $ticket);
        $target = Division::query()->findOrFail($r->integer('target_division_id'));

        return $this->result($r, $s->transfer($ticket, $r->user(), $target, $r->string('notes')), 'Ticket transferred');
    }

    private function result($request, Ticket $result, string $message): JsonResponse
    {
        return ApiResponse::success($request, $message, (new TicketResource($result->load(self::RELATIONS)))->resolve($request));
    }
}
