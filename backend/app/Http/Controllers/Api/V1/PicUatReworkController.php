<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ResolveUatFindingRequest;
use App\Http\Requests\Api\V1\SubmitUatRetestRequest;
use App\Http\Requests\Api\V1\UploadUatEvidenceRequest;
use App\Http\Resources\Api\V1\TicketAttachmentResource;
use App\Http\Resources\Api\V1\TicketResource;
use App\Http\Resources\Api\V1\TicketUatFindingResource;
use App\Models\Ticket;
use App\Models\TicketUatFinding;
use App\Services\TicketUatFindingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class PicUatReworkController extends Controller
{
    private const RELATIONS = ['requester.division', 'division', 'currentDivision', 'branch', 'application', 'applicationModule', 'category', 'requestedPriority', 'finalPriority', 'slaPolicy', 'workingCalendar', 'currentAssignee', 'assignments', 'attachments'];

    public function findings(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('develop', $ticket);
        $findings = $ticket->uatFindings()->with('reporter', 'assignee', 'resolver')->get();

        return ApiResponse::success($request, 'UAT findings retrieved for PIC rework', TicketUatFindingResource::collection($findings)->resolve($request));
    }

    public function startFinding(Request $request, Ticket $ticket, TicketUatFinding $finding, TicketUatFindingService $service): JsonResponse
    {
        Gate::authorize('develop', $ticket);
        if ($finding->ticket_id !== $ticket->id) {
            abort(404, 'Finding does not belong to this ticket.');
        }
        $finding = $service->startFinding($ticket, $finding, $request->user());

        return ApiResponse::success($request, 'PIC started working on UAT finding', (new TicketUatFindingResource($finding->load('histories')))->resolve($request));
    }

    public function resolveFinding(ResolveUatFindingRequest $request, Ticket $ticket, TicketUatFinding $finding, TicketUatFindingService $service): JsonResponse
    {
        Gate::authorize('develop', $ticket);
        if ($finding->ticket_id !== $ticket->id) {
            abort(404, 'Finding does not belong to this ticket.');
        }
        $finding = $service->resolveFinding($ticket, $finding, $request->user(), $request->string('resolution_notes'));

        return ApiResponse::success($request, 'UAT finding resolved successfully', (new TicketUatFindingResource($finding->load('histories')))->resolve($request));
    }

    public function submitRetest(SubmitUatRetestRequest $request, Ticket $ticket, TicketUatFindingService $service): JsonResponse
    {
        Gate::authorize('develop', $ticket);
        $ticket = $service->submitRetest($ticket, $request->user(), $request->boolean('requires_qa_retest'));

        return ApiResponse::success($request, 'Ticket successfully submitted for UAT Retest', (new TicketResource($ticket->load(self::RELATIONS)))->resolve($request));
    }

    public function uploadEvidence(UploadUatEvidenceRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('develop', $ticket);
        if (! in_array($request->string('category')->toString(), ['uat_finding_evidence', 'uat_retest_evidence'], true)) {
            abort(403, 'PIC may only upload UAT finding or retest evidence.');
        }
        if (! $ticket->assignments()->where('assigned_to', $request->user()->id)->where('is_current', true)->exists()) {
            abort(403, 'PIC assignment is not active for this ticket.');
        }
        if ($request->filled('uat_finding_id')) {
            abort_unless($ticket->uatFindings()->whereKey($request->integer('uat_finding_id'))->exists(), 422, 'Finding does not belong to this ticket.');
        }

        $file = $request->file('file');
        $disk = config('tickets.attachment_disk', 'local');
        $stored = Str::uuid()->toString();
        $path = $file->storeAs("tickets/{$ticket->id}", $stored, $disk);
        try {
            $attachment = DB::transaction(function () use ($request, $ticket, $file, $disk, $stored, $path) {
                return $ticket->attachments()->create([
                    'uploaded_by' => $request->user()->id,
                    'original_name' => $file->getClientOriginalName(),
                    'stored_name' => $stored,
                    'disk' => $disk,
                    'path' => $path,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize(),
                    'category' => $request->string('category'),
                    'visibility' => 'internal',
                    'uat_finding_id' => $request->input('uat_finding_id'),
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }

        return ApiResponse::success($request, 'PIC UAT evidence uploaded successfully', (new TicketAttachmentResource($attachment))->resolve($request), 201);
    }
}
