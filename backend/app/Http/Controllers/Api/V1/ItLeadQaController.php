<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignQaRequest;
use App\Http\Requests\Api\V1\TicketListRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketQaAssignmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ItLeadQaController extends Controller
{
    private const RELATIONS = ['requester.division', 'division', 'currentDivision', 'branch', 'application', 'applicationModule', 'category', 'requestedPriority', 'finalPriority', 'slaPolicy', 'workingCalendar', 'currentAssignee', 'assignments', 'attachments'];

    public function queue(TicketListRequest $request): JsonResponse
    {
        $query = Ticket::query()->where('status', TicketStatus::ReadyForQa)->with(self::RELATIONS);

        $query->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('ticket_number', 'like', '%'.$request->string('search').'%')->orWhere('title', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('category'), fn ($q) => $q->where('ticket_category_id', $request->integer('category')))
            ->when($request->filled('application'), fn ($q) => $q->where('application_id', $request->integer('application')))
            ->when($request->filled('priority'), fn ($q) => $q->where('final_priority_id', $request->integer('priority')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('submitted_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('submitted_at', '<=', $request->date('date_to')));

        $page = $query->oldest('ready_for_qa_at')->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            $request,
            'QA assignment queue retrieved',
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

    public function qaOptions(Request $request): JsonResponse
    {
        $qas = User::query()
            ->with(['role', 'division'])
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('key', 'qa')->where('is_active', true))
            ->orderBy('name')
            ->get();

        $data = $qas->map(fn ($qa) => [
            'id' => $qa->id,
            'name' => $qa->name,
            'email' => $qa->email,
            'division' => $qa->division ? ['id' => $qa->division->id, 'name' => $qa->division->name] : null,
        ]);

        return ApiResponse::success($request, 'QA options retrieved', $data->all());
    }

    public function qaWorkloads(Request $request): JsonResponse
    {
        $qas = User::query()
            ->with(['role', 'division'])
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('key', 'qa')->where('is_active', true))
            ->withCount([
                'qaAssignments as active_assignment_count' => fn ($q) => $q->where('is_current', true),
                'qaAssignments as critical_count' => fn ($q) => $q->where('is_current', true)->whereHas('ticket.finalPriority', fn ($q) => $q->where('key', 'critical')),
                'qaAssignments as high_count' => fn ($q) => $q->where('is_current', true)->whereHas('ticket.finalPriority', fn ($q) => $q->where('key', 'high')),
            ])
            ->orderBy('name')
            ->get();

        $data = $qas->map(fn ($qa) => [
            'id' => $qa->id,
            'name' => $qa->name,
            'email' => $qa->email,
            'division' => $qa->division ? ['id' => $qa->division->id, 'name' => $qa->division->name] : null,
            'active_assignment_count' => (int) $qa->active_assignment_count,
            'critical_count' => (int) $qa->critical_count,
            'high_count' => (int) $qa->high_count,
            'workload_indicator' => $qa->active_assignment_count >= 8 ? 'high' : ($qa->active_assignment_count >= 4 ? 'medium' : 'low'),
        ]);

        return ApiResponse::success($request, 'QA workloads retrieved', $data->all());
    }

    public function assignQa(AssignQaRequest $request, Ticket $ticket, TicketQaAssignmentService $service): JsonResponse
    {
        Gate::authorize('assignQa', $ticket);

        $ticket = $service->assign($ticket, $request->user(), $request->integer('qa_user_id'), $request->string('notes'));

        return ApiResponse::success($request, 'QA assignee successfully assigned', (new TicketResource($ticket))->resolve($request));
    }
}
