<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CompleteInternalTestRunRequest;
use App\Http\Requests\Api\V1\SaveInternalTestCaseRequest;
use App\Http\Requests\Api\V1\StartInternalTestRunRequest;
use App\Http\Requests\Api\V1\StoreInternalTestResultRequest;
use App\Http\Resources\Api\V1\TicketInternalTestCaseResource;
use App\Http\Resources\Api\V1\TicketInternalTestResultResource;
use App\Http\Resources\Api\V1\TicketInternalTestRunResource;
use App\Models\Ticket;
use App\Models\TicketInternalTestCase;
use App\Models\TicketInternalTestRun;
use App\Services\TicketDevelopmentService;
use App\Services\TicketInternalTestingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PicInternalTestingController extends Controller
{
    public function cases(Request $request, Ticket $ticket, TicketDevelopmentService $development): JsonResponse
    {
        $development->assertOwner($ticket, $request->user());

        return ApiResponse::success($request, 'Internal test cases retrieved', TicketInternalTestCaseResource::collection($ticket->internalTestCases()->get())->resolve($request));
    }

    public function storeCase(SaveInternalTestCaseRequest $request, Ticket $ticket, TicketInternalTestingService $service): JsonResponse
    {
        return ApiResponse::success($request, 'Internal test case created', (new TicketInternalTestCaseResource($service->createCase($ticket, $request->user(), $request->validated())))->resolve($request), 201);
    }

    public function updateCase(SaveInternalTestCaseRequest $request, Ticket $ticket, TicketInternalTestCase $case, TicketInternalTestingService $service): JsonResponse
    {
        return ApiResponse::success($request, 'Internal test case updated', (new TicketInternalTestCaseResource($service->updateCase($ticket, $case, $request->user(), $request->validated())))->resolve($request));
    }

    public function deactivateCase(Request $request, Ticket $ticket, TicketInternalTestCase $case, TicketInternalTestingService $service): JsonResponse
    {
        return ApiResponse::success($request, 'Internal test case deactivated', (new TicketInternalTestCaseResource($service->deactivateCase($ticket, $case, $request->user())))->resolve($request));
    }

    public function startRun(StartInternalTestRunRequest $request, Ticket $ticket, TicketInternalTestingService $service): JsonResponse
    {
        return ApiResponse::success($request, 'Internal testing started', (new TicketInternalTestRunResource($service->startRun($ticket, $request->user(), $request->validated())))->resolve($request), 201);
    }

    public function runs(Request $request, Ticket $ticket, TicketDevelopmentService $development): JsonResponse
    {
        $development->assertOwner($ticket, $request->user());

        return ApiResponse::success($request, 'Internal test runs retrieved', TicketInternalTestRunResource::collection($ticket->internalTestRuns()->with('results')->get())->resolve($request));
    }

    public function run(Request $request, Ticket $ticket, TicketInternalTestRun $run, TicketDevelopmentService $development): JsonResponse
    {
        $development->assertOwner($ticket, $request->user());
        abort_unless($run->ticket_id === $ticket->id, 404);

        return ApiResponse::success($request, 'Internal test run retrieved', (new TicketInternalTestRunResource($run->load('results')))->resolve($request));
    }

    public function result(StoreInternalTestResultRequest $request, Ticket $ticket, TicketInternalTestRun $run, TicketInternalTestingService $service): JsonResponse
    {
        return ApiResponse::success($request, 'Internal test result recorded', (new TicketInternalTestResultResource($service->recordResult($ticket, $run, $request->user(), $request->validated())))->resolve($request), 201);
    }

    public function complete(CompleteInternalTestRunRequest $request, Ticket $ticket, TicketInternalTestRun $run, TicketInternalTestingService $service): JsonResponse
    {
        return ApiResponse::success($request, 'Internal test run completed', (new TicketInternalTestRunResource($service->complete($ticket, $run, $request->user(), $request->input('summary'))))->resolve($request));
    }
}
