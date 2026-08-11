<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PublicActionAccessDenied;
use App\Exceptions\PublicActionVerificationFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RequestPublicTicketActionChallengeRequest;
use App\Http\Requests\Api\V1\SubmitPublicTicketActionRequest;
use App\Http\Requests\Api\V1\VerifyPublicTicketActionChallengeRequest;
use App\Services\PublicTicketActionService;
use App\Services\PublicTicketTrackingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicTicketActionController extends Controller
{
    public function available(Request $request, string $token, PublicTicketTrackingService $tracking, PublicTicketActionService $actions): JsonResponse
    {
        return ApiResponse::success($request, 'Available public ticket action retrieved.', $actions->available($tracking->lookup($token)));
    }

    public function challenge(RequestPublicTicketActionChallengeRequest $request, string $token, PublicTicketTrackingService $tracking, PublicTicketActionService $actions): JsonResponse
    {
        $ticket = $tracking->lookup($token);
        $data = $actions->challenge($ticket, $actions->trackingRecord($token), $request->validated('action'), $request->validated('email'));

        return ApiResponse::success($request, 'If the details are valid, a verification code has been sent.', $data, 202);
    }

    public function verify(VerifyPublicTicketActionChallengeRequest $request, string $token, PublicTicketTrackingService $tracking, PublicTicketActionService $actions): JsonResponse
    {
        $ticket = $tracking->lookup($token);
        try {
            $data = $actions->verify($ticket, $actions->trackingRecord($token), $request->validated('action'), $request->validated('challenge_token'), $request->validated('code'));
        } catch (PublicActionVerificationFailed) {
            return ApiResponse::error($request, 'The verification code is invalid or no longer available.', 'VERIFICATION_FAILED', 422);
        }

        return ApiResponse::success($request, 'Public ticket action access granted.', $data);
    }

    public function revoke(Request $request, string $token, PublicTicketTrackingService $tracking, PublicTicketActionService $actions): JsonResponse
    {
        $tracking->lookup($token);
        $actions->revoke($this->access($request, $token, $actions));

        return ApiResponse::success($request, 'Public ticket action access revoked.');
    }

    public function uat(SubmitPublicTicketActionRequest $request, string $token, PublicTicketTrackingService $tracking, PublicTicketActionService $actions): JsonResponse
    {
        return $this->mutate($request, $token, 'uat', $tracking, $actions);
    }

    public function confirmation(SubmitPublicTicketActionRequest $request, string $token, PublicTicketTrackingService $tracking, PublicTicketActionService $actions): JsonResponse
    {
        return $this->mutate($request, $token, 'confirmation', $tracking, $actions);
    }

    private function mutate(SubmitPublicTicketActionRequest $request, string $token, string $action, PublicTicketTrackingService $tracking, PublicTicketActionService $actions): JsonResponse
    {
        $ticket = $tracking->lookup($token);
        $processed = $actions->mutate(
            $ticket,
            $this->access($request, $token, $actions),
            $action,
            $request->validated('outcome'),
            $request->validated('notes'),
            $action === 'uat' ? ($request->file('files') ?? []) : [],
            $request->validated('idempotency_key'),
        );

        return ApiResponse::success($request, $processed['replay'] ? 'Public ticket action replayed.' : 'Public ticket action recorded.', $processed['result']);
    }

    private function access(Request $request, string $token, PublicTicketActionService $actions)
    {
        try {
            return $actions->authenticate($token, $request->header('X-Public-Action-Token') ?: $request->bearerToken());
        } catch (PublicActionAccessDenied) {
            throw new PublicActionAccessDenied;
        } catch (\Throwable) {
            throw new PublicActionAccessDenied;
        }
    }
}
