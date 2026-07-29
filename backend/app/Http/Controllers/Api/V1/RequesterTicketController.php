<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRequesterTicketRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Services\RequesterTicketService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RequesterTicketController extends Controller
{
    public function store(StoreRequesterTicketRequest $request, RequesterTicketService $service): JsonResponse
    {
        $user = $request->user();
        $idempotencyKey = $request->header('Idempotency-Key');
        if ($idempotencyKey !== null && (trim($idempotencyKey) === '' || strlen($idempotencyKey) > 255)) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['The Idempotency-Key header must contain between 1 and 255 characters.'],
            ]);
        }

        $files = $request->file('attachments', []);
        if (! is_array($files)) {
            $files = [$files];
        }

        $ticket = $service->createTicket($user, $request->validated(), $files, $idempotencyKey);

        $ticket->load(['requester', 'division', 'branch', 'attachments']);

        return ApiResponse::success(
            $request,
            'Ticket created and submitted',
            (new TicketResource($ticket))->resolve($request),
            201
        );
    }
}
