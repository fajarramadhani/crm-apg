<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveUatFindingRequest;
use App\Http\Requests\Api\V1\SaveUatScenarioRequest;
use App\Http\Requests\Api\V1\StoreUatResultRequest;
use App\Http\Requests\Api\V1\StoreUatRunRequest;
use App\Http\Requests\Api\V1\UploadUatEvidenceRequest;
use App\Http\Resources\Api\V1\TicketAttachmentResource;
use App\Http\Resources\Api\V1\TicketResource;
use App\Http\Resources\Api\V1\TicketUatFindingResource;
use App\Http\Resources\Api\V1\TicketUatResultResource;
use App\Http\Resources\Api\V1\TicketUatRunResource;
use App\Http\Resources\Api\V1\TicketUatScenarioResource;
use App\Models\Ticket;
use App\Models\TicketUatFinding;
use App\Models\TicketUatRun;
use App\Models\TicketUatScenario;
use App\Services\TicketUatExecutionService;
use App\Services\TicketUatFindingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class RequesterUatController extends Controller
{
    private const RELATIONS = ['requester.division', 'division', 'currentDivision', 'branch', 'application', 'applicationModule', 'category', 'requestedPriority', 'finalPriority', 'slaPolicy', 'workingCalendar', 'currentAssignee', 'assignments', 'attachments'];

    public function assignments(Request $request): JsonResponse
    {
        $user = $request->user();
        $tickets = Ticket::query()
            ->where('uat_assignee_id', $user->id)
            ->with(self::RELATIONS)
            ->latest('uat_assigned_at')
            ->get();

        return ApiResponse::success($request, 'Requester UAT assignments retrieved', TicketResource::collection($tickets)->resolve($request));
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        $ticket->load(self::RELATIONS);

        return ApiResponse::success($request, 'Requester UAT ticket details retrieved', (new TicketResource($ticket))->resolve($request));
    }

    public function start(Request $request, Ticket $ticket, TicketUatExecutionService $service): JsonResponse
    {
        Gate::authorize('executeUat', $ticket);
        $ticket = $service->start($ticket, $request->user());

        return ApiResponse::success($request, 'UAT testing started', (new TicketResource($ticket->load(self::RELATIONS)))->resolve($request));
    }

    // Scenarios
    public function scenarios(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        $scenarios = $ticket->uatScenarios()->where('is_active', true)->get();

        return ApiResponse::success($request, 'UAT scenarios retrieved', TicketUatScenarioResource::collection($scenarios)->resolve($request));
    }

    public function storeScenario(SaveUatScenarioRequest $request, Ticket $ticket): JsonResponse
    {
        // Allowed for QA/PIC or Owner Requester
        if ($ticket->uat_assignee_id !== $request->user()->id && ! $request->user()->hasRole(['qa', 'pic', 'it_lead'])) {
            abort(403, 'Unauthorized to create scenarios for this UAT.');
        }
        abort_unless(in_array($ticket->status, [TicketStatus::UatAssignment, TicketStatus::UatInProgress, TicketStatus::UatRetest], true), 409, 'Scenarios can only be managed during UAT.');

        $scenario = $ticket->uatScenarios()->create([
            'created_by' => $request->user()->id,
            'scenario_number' => $request->string('scenario_number'),
            'title' => $request->string('title'),
            'business_objective' => $request->string('business_objective'),
            'preconditions' => $request->string('preconditions'),
            'steps' => $request->input('steps'),
            'expected_result' => $request->string('expected_result'),
            'acceptance_criteria' => $request->input('acceptance_criteria'),
            'priority' => $request->string('priority'),
            'is_active' => true,
        ]);

        $ticket->histories()->create([
            'from_status' => $ticket->status->value,
            'to_status' => $ticket->status->value,
            'action' => 'uat_scenario_created',
            'actor_id' => $request->user()->id,
            'actor_role' => $request->user()->role?->key ?? 'unknown',
            'metadata' => ['scenario_number' => $scenario->scenario_number],
        ]);

        return ApiResponse::success($request, 'UAT scenario created successfully', (new TicketUatScenarioResource($scenario))->resolve($request), 201);
    }

    public function updateScenario(SaveUatScenarioRequest $request, Ticket $ticket, TicketUatScenario $scenario): JsonResponse
    {
        if ($scenario->ticket_id !== $ticket->id) {
            abort(404, 'Scenario does not belong to this ticket.');
        }

        if ($ticket->uat_assignee_id !== $request->user()->id && ! $request->user()->hasRole(['qa', 'pic', 'it_lead'])) {
            abort(403, 'Unauthorized to update scenarios for this UAT.');
        }
        abort_unless(in_array($ticket->status, [TicketStatus::UatAssignment, TicketStatus::UatInProgress, TicketStatus::UatRetest], true), 409, 'Scenarios can only be managed during UAT.');

        if ($scenario->results()->exists()) {
            abort(409, 'A used test scenario cannot be edited.');
        }

        $scenario->update($request->validated());

        return ApiResponse::success($request, 'UAT scenario updated successfully', (new TicketUatScenarioResource($scenario->fresh()))->resolve($request));
    }

    public function destroyScenario(Request $request, Ticket $ticket, TicketUatScenario $scenario): JsonResponse
    {
        if ($scenario->ticket_id !== $ticket->id) {
            abort(404, 'Scenario does not belong to this ticket.');
        }

        if ($ticket->uat_assignee_id !== $request->user()->id && ! $request->user()->hasRole(['qa', 'pic', 'it_lead'])) {
            abort(403, 'Unauthorized to deactivate scenarios for this UAT.');
        }
        abort_unless(in_array($ticket->status, [TicketStatus::UatAssignment, TicketStatus::UatInProgress, TicketStatus::UatRetest], true), 409, 'Scenarios can only be managed during UAT.');

        if ($scenario->results()->exists()) {
            $scenario->update(['is_active' => false]);

            return ApiResponse::success($request, 'UAT scenario deactivated successfully (retained for historical results)');
        }

        $scenario->delete();

        return ApiResponse::success($request, 'UAT scenario deleted successfully');
    }

    // Runs
    public function runs(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        $runs = $ticket->uatRuns()->with('results')->get();

        return ApiResponse::success($request, 'UAT runs retrieved', TicketUatRunResource::collection($runs)->resolve($request));
    }

    public function showRun(Request $request, Ticket $ticket, TicketUatRun $run): JsonResponse
    {
        Gate::authorize('view', $ticket);
        if ($run->ticket_id !== $ticket->id) {
            abort(404, 'UAT run does not belong to this ticket.');
        }

        return ApiResponse::success($request, 'UAT run details retrieved', (new TicketUatRunResource($run->load('results')))->resolve($request));
    }

    public function storeRun(StoreUatRunRequest $request, Ticket $ticket, TicketUatExecutionService $service): JsonResponse
    {
        $run = $service->startRun($ticket, $request->user(), $request->validated());

        return ApiResponse::success($request, 'UAT run started successfully', (new TicketUatRunResource($run))->resolve($request), 201);
    }

    public function storeResult(StoreUatResultRequest $request, Ticket $ticket, TicketUatRun $run, TicketUatExecutionService $service): JsonResponse
    {
        $result = $service->recordResult($ticket, $run, $request->user(), $request->validated());

        return ApiResponse::success($request, 'UAT scenario result recorded', (new TicketUatResultResource($result))->resolve($request));
    }

    public function completeRun(Request $request, Ticket $ticket, TicketUatRun $run, TicketUatExecutionService $service): JsonResponse
    {
        $run = $service->complete($ticket, $run, $request->user(), $request->string('summary'));

        return ApiResponse::success($request, 'UAT run completed', (new TicketUatRunResource($run))->resolve($request));
    }

    // Findings
    public function findings(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        $findings = $ticket->uatFindings()->with('reporter', 'assignee', 'resolver')->get();

        return ApiResponse::success($request, 'UAT findings retrieved', TicketUatFindingResource::collection($findings)->resolve($request));
    }

    public function storeFinding(SaveUatFindingRequest $request, Ticket $ticket, TicketUatFindingService $service): JsonResponse
    {
        $finding = $service->createFinding($ticket, $request->user(), $request->validated());

        return ApiResponse::success($request, 'UAT finding recorded successfully', (new TicketUatFindingResource($finding->load('histories')))->resolve($request));
    }

    public function verifyFinding(Request $request, Ticket $ticket, TicketUatFinding $finding, TicketUatFindingService $service): JsonResponse
    {
        if ($finding->ticket_id !== $ticket->id) {
            abort(404, 'Finding does not belong to this ticket.');
        }
        $finding = $service->verifyFinding($ticket, $finding, $request->user(), $request->string('notes'));

        return ApiResponse::success($request, 'UAT finding verified successfully', (new TicketUatFindingResource($finding->load('histories')))->resolve($request));
    }

    public function reopenFinding(Request $request, Ticket $ticket, TicketUatFinding $finding, TicketUatFindingService $service): JsonResponse
    {
        if ($finding->ticket_id !== $ticket->id) {
            abort(404, 'Finding does not belong to this ticket.');
        }
        $finding = $service->reopenFinding($ticket, $finding, $request->user(), $request->string('notes'));

        return ApiResponse::success($request, 'UAT finding reopened successfully', (new TicketUatFindingResource($finding->load('histories')))->resolve($request));
    }

    public function uploadEvidence(UploadUatEvidenceRequest $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        if ($ticket->uat_assignee_id !== $user->id) {
            abort(403, 'You are not the assigned UAT tester.');
        }
        if (! in_array($ticket->status, [TicketStatus::UatInProgress, TicketStatus::UatRetest])) {
            abort(409, 'UAT evidence can only be uploaded when UAT is in progress.');
        }
        if ($request->string('category')->toString() !== 'uat_evidence' && $request->string('category')->toString() !== 'uat_signoff_document') {
            abort(403, 'Requester may only upload requester UAT evidence.');
        }
        if ($request->filled('uat_finding_id')) {
            abort_unless($ticket->uatFindings()->whereKey($request->integer('uat_finding_id'))->exists(), 422, 'Finding does not belong to this ticket.');
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
                    'visibility' => 'requester',
                    'uat_finding_id' => $request->input('uat_finding_id'),
                ]);

                $ticket->histories()->create([
                    'from_status' => $ticket->status->value,
                    'to_status' => $ticket->status->value,
                    'action' => 'uat_evidence_uploaded',
                    'actor_id' => $user->id,
                    'actor_role' => 'requester',
                    'metadata' => [
                        'evidence_count' => $ticket->attachments()->whereIn('category', ['uat_evidence', 'uat_finding_evidence', 'uat_retest_evidence'])->count(),
                        'uat_finding_id' => $a->uat_finding_id,
                    ],
                ]);

                return $a;
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }

        return ApiResponse::success($request, 'UAT evidence uploaded successfully', (new TicketAttachmentResource($attachment))->resolve($request), 201);
    }
}
