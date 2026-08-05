<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PublicHistoryAccessDenied;
use App\Exceptions\PublicHistoryDeliveryFailed;
use App\Exceptions\PublicHistoryVerificationFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RequestPublicHistoryChallengeRequest;
use App\Http\Requests\Api\V1\VerifyPublicHistoryChallengeRequest;
use App\Http\Resources\Api\V1\PublicRequestHistoryResource;
use App\Services\PublicRequestHistoryService;
use App\Services\PublicTicketTrackingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicRequestHistoryController extends Controller
{
    public function challenge(RequestPublicHistoryChallengeRequest $request, PublicRequestHistoryService $service): JsonResponse
    {
        try {
            $data = $service->requestCode($request->validated('email'), (int) $request->validated('branch_id'));
        } catch (PublicHistoryDeliveryFailed) {
            return ApiResponse::error($request, 'The verification code could not be sent. Please try again later.', 'DELIVERY_UNAVAILABLE', 503);
        }

        return ApiResponse::success($request, 'If the details are valid, a verification code has been sent.', $data, 202);
    }

    public function verify(VerifyPublicHistoryChallengeRequest $request, PublicRequestHistoryService $service): JsonResponse
    {
        try {
            $data = $service->verify($request->validated('challenge_token'), $request->validated('code'));
        } catch (PublicHistoryVerificationFailed) {
            return ApiResponse::error($request, 'The verification code is invalid or no longer available.', 'VERIFICATION_FAILED', 422);
        }

        return ApiResponse::success($request, 'Request history access granted.', $data);
    }

    public function index(Request $request, PublicRequestHistoryService $service): JsonResponse
    {
        if ($request->hasAny(['email', 'identity', 'identity_type', 'branch', 'branch_id'])) {
            throw ValidationException::withMessages(['scope' => ['History scope cannot be overridden.']]);
        }
        $access = $this->access($request, $service);
        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);
        $tickets = $service->history($access, $perPage);
        $data = PublicRequestHistoryResource::collection($tickets->items())->resolve($request);

        return ApiResponse::success($request, 'Public request history retrieved.', $data, 200, [
            'pagination' => [
                'current_page' => $tickets->currentPage(), 'per_page' => $tickets->perPage(),
                'total' => $tickets->total(), 'last_page' => $tickets->lastPage(),
            ],
        ]);
    }

    public function revoke(Request $request, PublicRequestHistoryService $service): JsonResponse
    {
        $access = $this->access($request, $service);
        $service->revoke($access);

        return ApiResponse::success($request, 'Public request history access revoked.');
    }

    public function trackingLink(
        Request $request,
        string $ticketNumber,
        PublicRequestHistoryService $history,
        PublicTicketTrackingService $tracking,
    ): JsonResponse {
        $access = $this->access($request, $history);
        $ticket = $history->ownedTicket($access, $ticketNumber);
        $record = $ticket->publicTrackingTokens()->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('generation')->first();
        if (! $record) {
            throw new NotFoundHttpException;
        }
        $receipt = $tracking->receipt($record);

        return ApiResponse::success($request, 'Tracking link retrieved.', [
            'tracking_path' => '/track/'.$receipt['raw_token'],
            'tracking_expires_at' => $record->expires_at?->toISOString(),
        ]);
    }

    private function access(Request $request, PublicRequestHistoryService $service)
    {
        try {
            return $service->authenticate($request->bearerToken());
        } catch (PublicHistoryAccessDenied) {
            throw new PublicHistoryAccessDenied;
        } catch (\Throwable) {
            throw new PublicHistoryAccessDenied;
        }
    }
}
