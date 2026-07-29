<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\LegacyTransitionHandler;
use App\Services\WorkflowEngineService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Handles runtime dynamic workflow transitions for tickets.
 *
 * Only dynamic tickets (workflow_mode = 'dynamic') pass through this controller.
 * Legacy tickets use their existing controllers.
 */
final class DynamicWorkflowTransitionController extends Controller
{
    public function __construct(
        private WorkflowEngineService $engine,
        private LegacyTransitionHandler $legacyHandler,
    ) {}

    /**
     * GET /api/v1/tickets/{ticket}/workflow-status
     *
     * Returns current workflow state, type badge, and available actions for the actor.
     */
    public function status(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        // Determine workflow type
        $workflowType = $this->legacyHandler->workflowTypeBadge($ticket);

        if ($workflowType === 'legacy') {
            return ApiResponse::success($request, 'Workflow status retrieved', [
                'workflow_type' => 'legacy',
                'current_stage' => null,
                'workflow_code' => null,
                'workflow_version' => null,
                'available_actions' => [],
            ]);
        }

        $snapshot = $ticket->workflow_snapshot ?? [];

        return ApiResponse::success($request, 'Workflow status retrieved', [
            'workflow_type' => 'dynamic',
            'workflow_code' => $snapshot['workflow_code'] ?? null,
            'workflow_name' => $snapshot['workflow_name'] ?? null,
            'workflow_version' => $ticket->workflow_version,
            'current_stage' => $ticket->current_workflow_stage,
            'available_actions' => $this->engine->availableActions($ticket, $user),
        ]);
    }

    /**
     * POST /api/v1/tickets/{ticket}/workflow-transition
     *
     * Execute a dynamic workflow transition.
     *
     * Body:
     *   - action_key: string (required)
     *   - expected_stage: string (optional, for optimistic concurrency)
     *   - notes: string
     *   - reason: string
     *   - result_summary: string
     *   - external_party: string
     *   - external_ref: string
     */
    public function transition(Request $request, Ticket $ticket): JsonResponse
    {
        if ($ticket->workflow_mode !== 'dynamic') {
            return ApiResponse::error(
                $request,
                'Endpoint ini hanya untuk tiket dengan dynamic workflow.',
                'NOT_DYNAMIC_TICKET',
                422
            );
        }

        $data = $request->validate([
            'action_key' => ['required', 'string', 'max:50'],
            'expected_stage' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'result_summary' => ['nullable', 'string', 'max:2000'],
            'external_party' => ['nullable', 'string', 'max:200'],
            'external_ref' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();

        // Pre-flight canTransition check
        if (! $this->engine->canTransition($ticket, $user, $data['action_key'])) {
            return ApiResponse::error(
                $request,
                'Anda tidak memiliki izin untuk menjalankan aksi ini, atau aksi tidak tersedia pada status tiket saat ini.',
                'TRANSITION_FORBIDDEN',
                403
            );
        }

        try {
            $updatedTicket = $this->engine->executeTransition(
                $ticket,
                $user,
                $data['action_key'],
                $data,
                $data['expected_stage'] ?? null
            );
        } catch (ConflictHttpException $e) {
            return ApiResponse::error($request, $e->getMessage(), 'STAGE_CONFLICT', 409);
        } catch (HttpException $e) {
            return ApiResponse::error($request, $e->getMessage(), 'TRANSITION_ERROR', $e->getStatusCode());
        }

        return ApiResponse::success($request, 'Workflow transition executed successfully', [
            'ticket' => [
                'id' => $updatedTicket->id,
                'ticket_number' => $updatedTicket->ticket_number,
                'status' => $updatedTicket->status?->value,
                'current_workflow_stage' => $updatedTicket->current_workflow_stage,
                'workflow_type' => 'dynamic',
            ],
            'available_actions' => $this->engine->availableActions($updatedTicket, $user),
            'message' => 'Transisi berhasil dijalankan.',
        ]);
    }
}
