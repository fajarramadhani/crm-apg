<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Events\TicketSubmitted;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTicketRequest;
use App\Http\Requests\Api\V1\TicketActionRequest;
use App\Http\Requests\Api\V1\TicketListRequest;
use App\Http\Requests\Api\V1\UpdateTicketRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Http\Resources\Api\V1\TicketStatusHistoryResource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketNumberGenerator;
use App\Services\TicketTransitionService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    private const RELATIONS = ['requester', 'division', 'currentDivision', 'branch', 'application', 'applicationModule', 'category', 'requestedPriority', 'finalPriority', 'slaPolicy', 'workingCalendar', 'currentAssignee', 'attachments', 'releaseOwner', 'approvalRequests.steps.approver'];

    public function index(TicketListRequest $request): JsonResponse
    {
        $user = $request->user();
        $query = Ticket::query()->with(self::RELATIONS);
        if ($user->role?->key === 'requester') {
            $query->where('requester_id', $user->id);
        }
        $this->filters($query, $request);
        $items = $query->latest()->paginate($request->integer('per_page', 20));

        return ApiResponse::success($request, 'Tickets retrieved', TicketResource::collection($items->items())->resolve($request), meta: ['pagination' => $this->pagination($items)]);
    }

    public function store(StoreTicketRequest $request, TicketNumberGenerator $numbers): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        if (! $user->division_id) {
            return ApiResponse::validationError($request, ['division' => ['Your account must have a division before creating a ticket.']]);
        }
        $ticket = DB::transaction(function () use ($request, $numbers, $user): Ticket {
            $ticket = Ticket::query()->create([...$request->validated(), 'ticket_number' => $numbers->next(), 'requester_id' => $user->id, 'division_id' => $user->division_id, 'branch_id' => $user->branch_id, 'current_division_id' => $user->division_id, 'status' => TicketStatus::PendingValidation, 'submitted_at' => now()]);
            $role = $user->role?->key ?? 'requester';
            $ticket->histories()->create(['from_status' => null, 'to_status' => TicketStatus::Draft->value, 'action' => 'created', 'actor_id' => $user->id, 'actor_role' => $role]);
            $ticket->histories()->create(['from_status' => TicketStatus::Draft->value, 'to_status' => TicketStatus::PendingValidation->value, 'action' => 'submitted', 'actor_id' => $user->id, 'actor_role' => $role]);

            return $ticket;
        });
        TicketSubmitted::dispatch($ticket);

        return ApiResponse::success($request, 'Ticket created and submitted', $this->resource($ticket, $request), 201);
    }

    public function show(TicketListRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        $ticket->load([...self::RELATIONS, 'histories.actor', 'comments' => fn ($q) => $request->user()->id === $ticket->requester_id ? $q->where('is_internal', false)->with('user') : $q->with('user')]);

        return ApiResponse::success($request, 'Ticket retrieved', (new TicketResource($ticket))->resolve($request));
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $ticket->update($request->validated());
        $changed = collect($ticket->getChanges())->except(['updated_at'])->keys()->values()->all();
        $ticket->histories()->create(['from_status' => $ticket->status->value, 'to_status' => $ticket->status->value, 'action' => 'updated', 'actor_id' => $request->user()->id, 'actor_role' => $request->user()->role?->key ?? 'requester', 'metadata' => ['changed_fields' => $changed]]);

        return ApiResponse::success($request, 'Ticket updated', $this->resource($ticket, $request));
    }

    public function resubmit(TicketActionRequest $request, Ticket $ticket, TicketTransitionService $transitions): JsonResponse
    {
        Gate::authorize('update', $ticket);

        return ApiResponse::success($request, 'Ticket resubmitted', $this->resource($transitions->resubmit($ticket, $request->user(), $request->input('notes')), $request));
    }

    public function cancel(TicketActionRequest $request, Ticket $ticket, TicketTransitionService $transitions): JsonResponse
    {
        Gate::authorize('cancel', $ticket);

        return ApiResponse::success($request, 'Ticket cancelled', $this->resource($transitions->cancel($ticket, $request->user(), $request->input('notes')), $request));
    }

    public function history(TicketListRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        $history = $ticket->histories()->with('actor')->get();

        return ApiResponse::success($request, 'Ticket history retrieved', TicketStatusHistoryResource::collection($history)->resolve($request));
    }

    private function resource(Ticket $ticket, $request): array
    {
        return (new TicketResource($ticket->load(self::RELATIONS)))->resolve($request);
    }

    private function filters(Builder $query, TicketListRequest $request): void
    {
        $query->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('ticket_number', 'like', '%'.$request->string('search').'%')->orWhere('title', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('category'), fn ($q) => $q->where('ticket_category_id', $request->integer('category')))
            ->when($request->filled('application'), fn ($q) => $q->where('application_id', $request->integer('application')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('date_to')));
    }

    private function pagination($paginator): array
    {
        return ['current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'last_page' => $paginator->lastPage()];
    }
}
