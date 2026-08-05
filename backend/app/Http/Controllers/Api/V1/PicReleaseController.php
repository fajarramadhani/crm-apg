<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChecklistDecisionRequest;
use App\Http\Requests\Api\V1\SaveReleasePlanRequest;
use App\Http\Requests\Api\V1\SaveRollbackPlanRequest;
use App\Http\Requests\Api\V1\UploadReleaseEvidenceRequest;
use App\Http\Resources\Api\V1\TicketAttachmentResource;
use App\Http\Resources\Api\V1\TicketReleaseChecklistItemResource;
use App\Http\Resources\Api\V1\TicketReleasePlanResource;
use App\Http\Resources\Api\V1\TicketRollbackPlanResource;
use App\Models\Ticket;
use App\Models\TicketReleaseChecklistItem;
use App\Services\TicketAttachmentService;
use App\Services\TicketReleasePreparationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class PicReleaseController extends Controller
{
    public function preparation(Request $request, Ticket $ticket, TicketReleasePreparationService $service): JsonResponse
    {
        Gate::authorize('viewReleasePreparation', $ticket);

        return app(ItLeadReleaseController::class)->preparation($request, $ticket);
    }

    public function storePlan(SaveReleasePlanRequest $request, Ticket $ticket, TicketReleasePreparationService $service): JsonResponse
    {
        Gate::authorize('manageReleasePreparation', $ticket);
        $plan = $service->createPlan($ticket, $request->user(), $request->validated());

        return ApiResponse::success($request, 'PIC release plan created', (new TicketReleasePlanResource($plan))->resolve($request), 201);
    }

    public function storeRollback(SaveRollbackPlanRequest $request, Ticket $ticket, TicketReleasePreparationService $service): JsonResponse
    {
        Gate::authorize('manageReleasePreparation', $ticket);
        $plan = $service->createRollback($ticket, $request->user(), $request->validated());

        return ApiResponse::success($request, 'PIC rollback plan created', (new TicketRollbackPlanResource($plan))->resolve($request), 201);
    }

    public function checklistDecision(ChecklistDecisionRequest $request, Ticket $ticket, TicketReleaseChecklistItem $item, TicketReleasePreparationService $service): JsonResponse
    {
        Gate::authorize('manageReleasePreparation', $ticket);
        $item = $service->updateChecklist($item, $ticket, $request->user(), $request->string('status')->toString(), $request->string('notes')->toString(), $request->integer('expected_version'));

        return ApiResponse::success($request, 'PIC release checklist updated', (new TicketReleaseChecklistItemResource($item))->resolve($request));
    }

    public function uploadEvidence(UploadReleaseEvidenceRequest $request, Ticket $ticket, TicketAttachmentService $attachments): JsonResponse
    {
        Gate::authorize('manageReleasePreparation', $ticket);
        $file = $request->file('file');
        $attachment = $attachments->store($ticket, $file, $request->user()->id, $request->string('category')->toString(), 'internal');

        return ApiResponse::success($request, 'Release evidence uploaded', (new TicketAttachmentResource($attachment))->resolve($request), 201);
    }
}
