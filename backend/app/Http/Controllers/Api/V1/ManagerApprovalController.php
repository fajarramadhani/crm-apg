<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApprovalDecisionRequest;
use App\Http\Resources\Api\V1\TicketApprovalRequestResource;
use App\Models\Ticket;
use App\Models\TicketApprovalRequest;
use App\Services\TicketApprovalDecisionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ManagerApprovalController extends Controller
{
    public function queue(Request $request): JsonResponse
    {
        $items = TicketApprovalRequest::query()->where('status', 'pending')->whereHas('ticket', fn ($q) => $q->where('division_id', $request->user()->division_id))->whereHas('steps', fn ($q) => $q->where('step_type', 'business_approval')->where('approver_id', $request->user()->id)->where('status', 'pending'))->with(['ticket.requester', 'steps.approver'])->latest('requested_at')->get();

        return ApiResponse::success($request, 'Business approval queue retrieved', TicketApprovalRequestResource::collection($items)->resolve($request));
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('businessApprove', $ticket);
        $approval = $ticket->approvalRequests()->where('status', 'pending')->latest('cycle_number')->with(['ticket', 'steps.approver', 'histories.actor'])->firstOrFail();

        return ApiResponse::success($request, 'Business approval retrieved', (new TicketApprovalRequestResource($approval))->resolve($request));
    }

    public function decide(ApprovalDecisionRequest $request, Ticket $ticket, string $decision, TicketApprovalDecisionService $service): JsonResponse
    {
        Gate::authorize('businessApprove', $ticket);
        $approval = $ticket->approvalRequests()->where('status', 'pending')->latest('cycle_number')->firstOrFail();
        $approval = $service->decide($approval, 'business_approval', $request->user(), $decision === 'approve' ? 'approved' : 'rejected', $request->string('notes')->toString(), $request->integer('expected_version'));

        return ApiResponse::success($request, 'Business approval decision recorded', (new TicketApprovalRequestResource($approval))->resolve($request));
    }
}
