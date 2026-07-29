<?php

namespace App\Services;

use App\Models\Ticket;

/**
 * Wrapper for legacy ticket transitions.
 *
 * Routes transitions for tickets where workflow_mode IS NULL or 'legacy'.
 * This handler delegates to existing hardcoded business logic, preserving
 * backward-compatibility without any changes to legacy flow.
 *
 * Dynamic tickets MUST NOT pass through this handler.
 */
final class LegacyTransitionHandler
{
    /**
     * Determine whether a ticket should use the legacy handler.
     */
    public function isLegacyTicket(Ticket $ticket): bool
    {
        return $ticket->workflow_mode === null || $ticket->workflow_mode === 'legacy';
    }

    /**
     * Guard: throw if this handler is called with a dynamic ticket.
     *
     * @throws \LogicException
     */
    public function assertLegacyTicket(Ticket $ticket): void
    {
        if (! $this->isLegacyTicket($ticket)) {
            throw new \LogicException(
                "LegacyTransitionHandler called on a dynamic ticket (ID: {$ticket->id}). "
                .'Use WorkflowEngineService for dynamic tickets.'
            );
        }
    }

    /**
     * Return the workflow_type badge for API responses.
     */
    public function workflowTypeBadge(Ticket $ticket): string
    {
        if ($ticket->workflow_mode === 'dynamic') {
            return 'dynamic';
        }

        return 'legacy';
    }
}
