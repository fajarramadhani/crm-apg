<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReopenQaDefectRequest;
use App\Http\Requests\Api\V1\SaveQaDefectRequest;
use App\Http\Requests\Api\V1\SaveQaTestCaseRequest;
use App\Http\Requests\Api\V1\StoreQaTestResultRequest;
use App\Http\Requests\Api\V1\StoreQaTestRunRequest;
use App\Http\Requests\Api\V1\UploadQaEvidenceRequest;
use App\Http\Requests\Api\V1\VerifyQaDefectRequest;
use App\Http\Resources\Api\V1\TicketAttachmentResource;
use App\Http\Resources\Api\V1\TicketQaDefectResource;
use App\Http\Resources\Api\V1\TicketQaTestCaseResource;
use App\Http\Resources\Api\V1\TicketQaTestResultResource;
use App\Http\Resources\Api\V1\TicketQaTestRunResource;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Ticket;
use App\Models\TicketQaDefect;
use App\Models\TicketQaTestCase;
use App\Models\TicketQaTestRun;
use App\Services\TicketQaDefectService;
use App\Services\TicketQaExecutionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class QaController extends Controller
{
    private const RELATIONS = ['requester.division', 'division', 'currentDivision', 'branch', 'application', 'applicationModule', 'category', 'requestedPriority', 'finalPriority', 'slaPolicy', 'workingCalendar', 'currentAssignee', 'assignments', 'attachments'];

    public function assignments(Request $request): JsonResponse
    {
        $query = Ticket::query()
            ->where('qa_assignee_id', $request->user()->id)
            ->with(self::RELATIONS)
            ->withCount([
                'qaDefects as open_defect_count' => fn ($q) => $q->whereIn('status', ['open', 'in_progress', 'reopened']),
            ]);

        $page = $query->latest('qa_assigned_at')->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            $request,
            'QA assignments retrieved',
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

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('executeQa', $ticket);
        $ticket->load([...self::RELATIONS, 'histories.actor', 'comments.user', 'qaTestCases', 'qaTestRuns', 'qaDefects']);

        return ApiResponse::success($request, 'QA ticket detail retrieved', (new TicketResource($ticket))->resolve($request));
    }

    public function start(Request $request, Ticket $ticket, TicketQaExecutionService $service): JsonResponse
    {
        Gate::authorize('executeQa', $ticket);
        $ticket = $service->start($ticket, $request->user());

        return ApiResponse::success($request, 'QA testing started', (new TicketResource($ticket->load(self::RELATIONS)))->resolve($request));
    }

    // Test Cases
    public function cases(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('executeQa', $ticket);
        $cases = $ticket->qaTestCases()->where('is_active', true)->get();

        return ApiResponse::success($request, 'QA test cases retrieved', TicketQaTestCaseResource::collection($cases)->resolve($request));
    }

    public function storeCase(SaveQaTestCaseRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('executeQa', $ticket);

        $case = $ticket->qaTestCases()->create([
            'created_by' => $request->user()->id,
            'case_number' => $request->string('case_number'),
            'title' => $request->string('title'),
            'test_type' => $request->string('test_type'),
            'preconditions' => $request->string('preconditions'),
            'steps' => $request->input('steps'),
            'expected_result' => $request->string('expected_result'),
            'priority' => $request->string('priority'),
            'is_regression' => $request->boolean('is_regression'),
            'is_active' => true,
        ]);

        return ApiResponse::success($request, 'Test case created', (new TicketQaTestCaseResource($case))->resolve($request));
    }

    public function updateCase(SaveQaTestCaseRequest $request, Ticket $ticket, TicketQaTestCase $case): JsonResponse
    {
        Gate::authorize('executeQa', $ticket);

        // Nested resource validation
        if ($case->ticket_id !== $ticket->id) {
            abort(404, 'Test case does not belong to this ticket.');
        }

        $case->update([
            'case_number' => $request->string('case_number'),
            'title' => $request->string('title'),
            'test_type' => $request->string('test_type'),
            'preconditions' => $request->string('preconditions'),
            'steps' => $request->input('steps'),
            'expected_result' => $request->string('expected_result'),
            'priority' => $request->string('priority'),
            'is_regression' => $request->boolean('is_regression'),
        ]);

        return ApiResponse::success($request, 'Test case updated', (new TicketQaTestCaseResource($case))->resolve($request));
    }

    public function destroyCase(Request $request, Ticket $ticket, TicketQaTestCase $case): JsonResponse
    {
        Gate::authorize('executeQa', $ticket);

        // Nested resource validation
        if ($case->ticket_id !== $ticket->id) {
            abort(404, 'Test case does not belong to this ticket.');
        }

        // Soft deactivation instead of hard delete
        $case->update(['is_active' => false]);

        return ApiResponse::success($request, 'Test case deactivated successfully');
    }

    // Test Runs
    public function runs(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('executeQa', $ticket);
        $runs = $ticket->qaTestRuns()->with('results.executor')->get();

        return ApiResponse::success($request, 'QA test runs retrieved', TicketQaTestRunResource::collection($runs)->resolve($request));
    }

    public function storeRun(StoreQaTestRunRequest $request, Ticket $ticket, TicketQaExecutionService $service): JsonResponse
    {
        Gate::authorize('executeQa', $ticket);
        $run = $service->startRun($ticket, $request->user(), $request->validated());

        return ApiResponse::success($request, 'Test run started', (new TicketQaTestRunResource($run))->resolve($request));
    }

    public function showRun(Request $request, Ticket $ticket, TicketQaTestRun $run): JsonResponse
    {
        Gate::authorize('executeQa', $ticket);

        // Nested resource validation
        if ($run->ticket_id !== $ticket->id) {
            abort(404, 'Test run does not belong to this ticket.');
        }

        $run->load('results.executor', 'results.testCase');

        return ApiResponse::success($request, 'Test run details retrieved', (new TicketQaTestRunResource($run))->resolve($request));
    }

    public function storeResult(StoreQaTestResultRequest $request, Ticket $ticket, TicketQaTestRun $run, TicketQaExecutionService $service): JsonResponse
    {
        Gate::authorize('executeQa', $ticket);

        // Nested resource validation
        if ($run->ticket_id !== $ticket->id) {
            abort(404, 'Test run does not belong to this ticket.');
        }

        $result = $service->recordResult($ticket, $run, $request->user(), $request->validated());

        return ApiResponse::success($request, 'Test case result recorded', (new TicketQaTestResultResource($result))->resolve($request));
    }

    public function completeRun(Request $request, Ticket $ticket, TicketQaTestRun $run, TicketQaExecutionService $service): JsonResponse
    {
        Gate::authorize('executeQa', $ticket);

        // Nested resource validation
        if ($run->ticket_id !== $ticket->id) {
            abort(404, 'Test run does not belong to this ticket.');
        }

        $run = $service->complete($ticket, $run, $request->user(), $request->string('summary'));

        return ApiResponse::success($request, 'QA test run completed', (new TicketQaTestRunResource($run))->resolve($request));
    }

    // Defects
    public function defects(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('executeQa', $ticket);
        $defects = $ticket->qaDefects()->with('reporter', 'assignee', 'resolver')->get();

        return ApiResponse::success($request, 'QA defects retrieved', TicketQaDefectResource::collection($defects)->resolve($request));
    }

    public function storeDefect(SaveQaDefectRequest $request, Ticket $ticket, TicketQaDefectService $service): JsonResponse
    {
        Gate::authorize('executeQa', $ticket);
        $defect = $service->createDefect($ticket, $request->user(), $request->validated());

        return ApiResponse::success($request, 'QA defect recorded successfully', (new TicketQaDefectResource($defect->load('histories')))->resolve($request));
    }

    public function updateDefect(SaveQaDefectRequest $request, Ticket $ticket, TicketQaDefect $defect, TicketQaDefectService $service): JsonResponse
    {
        Gate::authorize('executeQa', $ticket);

        // Nested resource validation
        if ($defect->ticket_id !== $ticket->id) {
            abort(404, 'Defect does not belong to this ticket.');
        }

        $defect = $service->updateDefect($ticket, $defect, $request->user(), $request->validated());

        return ApiResponse::success($request, 'QA defect updated successfully', (new TicketQaDefectResource($defect->load('histories')))->resolve($request));
    }

    public function verifyDefect(VerifyQaDefectRequest $request, Ticket $ticket, TicketQaDefect $defect, TicketQaDefectService $service): JsonResponse
    {
        Gate::authorize('executeQa', $ticket);

        // Nested resource validation
        if ($defect->ticket_id !== $ticket->id) {
            abort(404, 'Defect does not belong to this ticket.');
        }

        $defect = $service->verifyDefect($ticket, $defect, $request->user(), $request->string('notes'));

        return ApiResponse::success($request, 'QA defect verified successfully', (new TicketQaDefectResource($defect->load('histories')))->resolve($request));
    }

    public function reopenDefect(ReopenQaDefectRequest $request, Ticket $ticket, TicketQaDefect $defect, TicketQaDefectService $service): JsonResponse
    {
        Gate::authorize('executeQa', $ticket);

        // Nested resource validation
        if ($defect->ticket_id !== $ticket->id) {
            abort(404, 'Defect does not belong to this ticket.');
        }

        $defect = $service->reopenDefect($ticket, $defect, $request->user(), $request->string('notes'));

        return ApiResponse::success($request, 'QA defect reopened successfully', (new TicketQaDefectResource($defect->load('histories')))->resolve($request));
    }

    public function uploadEvidence(UploadQaEvidenceRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('executeQa', $ticket);

        $user = $request->user();
        if ($ticket->qa_assignee_id !== $user->id) {
            abort(403, 'You are not the assigned QA for this ticket.');
        }
        if (! in_array($ticket->status, [TicketStatus::QaInProgress, TicketStatus::QaRetest])) {
            abort(409, 'QA evidence can only be uploaded when QA is in progress.');
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
                    'visibility' => 'internal',
                    'defect_id' => $request->input('defect_id'),
                ]);

                $ticket->histories()->create([
                    'from_status' => $ticket->status->value,
                    'to_status' => $ticket->status->value,
                    'action' => 'qa_evidence_uploaded',
                    'actor_id' => $user->id,
                    'actor_role' => 'qa',
                    'metadata' => [
                        'evidence_count' => $ticket->attachments()->whereIn('category', ['qa_evidence', 'defect_evidence', 'retest_evidence'])->count(),
                        'defect_id' => $a->defect_id,
                    ],
                ]);

                return $a;
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }

        return ApiResponse::success($request, 'QA evidence uploaded successfully', (new TicketAttachmentResource($attachment))->resolve($request), 201);
    }
}
