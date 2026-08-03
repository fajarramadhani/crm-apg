<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ManagePublicTicketTrackingRequest;
use App\Http\Resources\Api\V1\PublicTicketTrackingResource;
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

    public function rotate(ManagePublicTicketTrackingRequest $request, Ticket $ticket, PublicTicketTrackingService $service): JsonResponse
    {
        Gate::authorize('managePublicTracking', $ticket);
        $issued = $service->rotate($ticket, $request->user(), trim($request->string('reason')->toString()));

        return ApiResponse::success($request, 'Public tracking access rotated', [
            'tracking_token' => $issued['raw_token'],
            'tracking_url' => $issued['tracking_url'],
            'tracking_expires_at' => $issued['record']->expires_at?->toISOString(),
        ]);
    }

    public function revoke(ManagePublicTicketTrackingRequest $request, Ticket $ticket, PublicTicketTrackingService $service): JsonResponse
    {
        Gate::authorize('managePublicTracking', $ticket);
        $service->revoke($ticket, $request->user(), trim($request->string('reason')->toString()));

        return ApiResponse::success($request, 'Public tracking access revoked');
    }
}
