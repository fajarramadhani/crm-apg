<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDevelopmentUpdateRequest;
use App\Http\Requests\Api\V1\StoreTicketWorklogRequest;
use App\Http\Requests\Api\V1\UploadDevelopmentEvidenceRequest;
use App\Http\Resources\Api\V1\TicketAttachmentResource;
use App\Http\Resources\Api\V1\TicketDevelopmentUpdateResource;
use App\Http\Resources\Api\V1\TicketResource;
use App\Http\Resources\Api\V1\TicketWorklogResource;
use App\Models\Ticket;
use App\Services\TicketDevelopmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class PicDevelopmentController extends Controller
{
    public function start(Request $request, Ticket $ticket, TicketDevelopmentService $service): JsonResponse
    {
        $ticket = $service->start($ticket, $request->user())->load(['requester', 'division', 'currentDivision', 'application', 'category', 'finalPriority', 'slaPolicy', 'currentAssignee']);

        return ApiResponse::success($request, 'Development started', (new TicketResource($ticket))->resolve($request));
    }

    public function worklogs(Request $request, Ticket $ticket, TicketDevelopmentService $service): JsonResponse
    {
        $service->assertOwner($ticket, $request->user());

        return ApiResponse::success($request, 'Worklogs retrieved', TicketWorklogResource::collection($ticket->worklogs()->with('user')->get())->resolve($request));
    }

    public function addWorklog(StoreTicketWorklogRequest $request, Ticket $ticket, TicketDevelopmentService $service): JsonResponse
    {
        return ApiResponse::success($request, 'Worklog added', (new TicketWorklogResource($service->addWorklog($ticket, $request->user(), $request->validated())))->resolve($request), 201);
    }

    public function updates(Request $request, Ticket $ticket, TicketDevelopmentService $service): JsonResponse
    {
        $service->assertOwner($ticket, $request->user());

        return ApiResponse::success($request, 'Development updates retrieved', TicketDevelopmentUpdateResource::collection($ticket->developmentUpdates()->with('creator')->get())->resolve($request));
    }

    public function addUpdate(StoreDevelopmentUpdateRequest $request, Ticket $ticket, TicketDevelopmentService $service): JsonResponse
    {
        return ApiResponse::success($request, 'Development progress updated', (new TicketDevelopmentUpdateResource($service->updateProgress($ticket, $request->user(), $request->validated())))->resolve($request), 201);
    }

    public function evidence(UploadDevelopmentEvidenceRequest $request, Ticket $ticket, TicketDevelopmentService $service): JsonResponse
    {
        $user = $request->user();
        $service->assertOwner($ticket, $user);

        if (! in_array($ticket->status, [TicketStatus::DevelopmentInProgress, TicketStatus::InternalTesting])) {
            abort(409, 'PIC evidence can only be uploaded during development or internal testing.');
        }

        $file = $request->file('file');
        $disk = config('tickets.attachment_disk', 'local');
        $stored = Str::uuid()->toString();
        $path = $file->storeAs("tickets/{$ticket->id}", $stored, $disk);
        try {
            $attachment = DB::transaction(function () use ($request, $ticket, $file, $disk, $stored, $path, $user) {
                $a = $ticket->attachments()->create([
                    'uploaded_by' => $user->id,
                    'original_name' => $file->getClientOriginalName(),
                    'stored_name' => $stored,
                    'disk' => $disk,
                    'path' => $path,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize(),
                    'category' => $request->string('category'),
                    'visibility' => $request->input('visibility', 'internal'),
                    'defect_id' => $request->input('defect_id'),
                ]);

                $ticket->histories()->create([
                    'from_status' => $ticket->status->value,
                    'to_status' => $ticket->status->value,
                    'action' => 'development_evidence_uploaded',
                    'actor_id' => $user->id,
                    'actor_role' => 'pic',
                    'metadata' => [
                        'evidence_count' => $ticket->attachments()->whereIn('category', ['development_evidence', 'test_evidence', 'log', 'documentation'])->count(),
                        'defect_id' => $a->defect_id,
                    ],
                ]);

                return $a;
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }

        return ApiResponse::success($request, 'Evidence uploaded', (new TicketAttachmentResource($attachment))->resolve($request), 201);
    }
}
