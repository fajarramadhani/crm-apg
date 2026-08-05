<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ManagePublicTicketTrackingRequest;
use App\Http\Resources\Api\V1\PublicTicketTrackingResource;
use App\Http\Resources\Api\V1\PublicTrackingAccessResource;
use App\Models\Ticket;
use App\Services\PublicTicketTrackingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PublicTicketTrackingController extends Controller
{
    public function show(Request $request, string $token, PublicTicketTrackingService $service): JsonResponse
    {
        $ticket = $service->lookup($token);

        return ApiResponse::success(
            $request,
            'Ticket tracking retrieved',
            (new PublicTicketTrackingResource($ticket))->resolve($request),
        );
    }

    public function access(Request $request, Ticket $ticket, PublicTicketTrackingService $service): JsonResponse
    {
        Gate::authorize('managePublicTracking', $ticket);

        return ApiResponse::success($request, 'Public tracking access retrieved',
            (new PublicTrackingAccessResource($service->status($ticket)))->resolve($request));
    }

    public function issue(ManagePublicTicketTrackingRequest $request, Ticket $ticket, PublicTicketTrackingService $service): JsonResponse
    {
        $issued = $service->issue(
            $ticket,
            $request->user(),
            trim($request->string('reason')->toString()),
            $request->validated('idempotency_key'),
        );

        return ApiResponse::success($request, 'Public tracking access created', $this->issuedData($issued));
    }

    public function rotate(ManagePublicTicketTrackingRequest $request, Ticket $ticket, PublicTicketTrackingService $service): JsonResponse
    {
        $issued = $service->rotate(
            $ticket,
            $request->user(),
            trim($request->string('reason')->toString()),
            $request->validated('idempotency_key'),
        );

        return ApiResponse::success($request, 'Public tracking access rotated', $this->issuedData($issued));
    }

    public function revoke(ManagePublicTicketTrackingRequest $request, Ticket $ticket, PublicTicketTrackingService $service): JsonResponse
    {
        $service->revoke(
            $ticket,
            $request->user(),
            trim($request->string('reason')->toString()),
            $request->validated('idempotency_key'),
        );

        return ApiResponse::success($request, 'Public tracking access revoked');
    }

    private function issuedData(array $issued): array
    {
        return [
            'tracking_url' => $issued['tracking_url'],
            'tracking_expires_at' => $issued['record']->expires_at?->toISOString(),
        ];
    }
}
