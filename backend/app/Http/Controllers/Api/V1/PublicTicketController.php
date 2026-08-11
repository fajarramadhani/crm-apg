<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePublicTicketRequest;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Division;
use App\Models\TicketCategory;
use App\Services\RequesterTicketService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicTicketController extends Controller
{
    public function options(Request $request): JsonResponse
    {
        $options = fn (string $model) => $model::query()->active()->orderBy('name')->get(['id', 'code', 'name']);

        return ApiResponse::success($request, 'Public ticket form options retrieved', [
            'branches' => $options(Branch::class),
            'divisions' => $options(Division::class),
            'categories' => $options(TicketCategory::class),
            'applications' => $options(Application::class),
        ]);
    }

    public function store(StorePublicTicketRequest $request, RequesterTicketService $service): JsonResponse
    {
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        if (strlen($idempotencyKey) < 32 || strlen($idempotencyKey) > 255) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['The Idempotency-Key header must contain between 32 and 255 characters.'],
            ]);
        }

        $files = $request->file('attachments', []);
        $result = $service->createPublicTicket($request->validated(), is_array($files) ? $files : [$files], $idempotencyKey);
        $ticket = $result->ticket;
        $ticket->loadMissing('branch:id,name');

        return ApiResponse::success($request, 'Ticket request submitted', [
            'ticket_number' => $ticket->ticket_number,
            'requester_name' => $ticket->requester_name,
            'branch_name' => $ticket->branch?->name,
            'title' => $ticket->title,
            'submitted_at' => $ticket->submitted_at?->toISOString(),
            'tracking_token' => $result->rawToken,
            'tracking_url' => $result->trackingUrl,
            'tracking_expires_at' => $result->trackingToken->expires_at?->toISOString(),
        ], 201);
    }
}
