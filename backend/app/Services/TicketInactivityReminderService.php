<?php

namespace App\Services;

use App\Models\SlaEscalationPolicy;
use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use Carbon\Carbon;

class TicketInactivityReminderService
{
    public function __construct(private TicketNotificationService $notificationService) {}

    public function scanAndAlert(): array
    {
        $stats = [
            'tickets_scanned' => 0,
            'alerts_sent' => 0,
            'failures' => 0,
        ];

        $policiesByPriority = SlaEscalationPolicy::query()
            ->where('is_active', true)
            ->whereNotNull('inactivity_threshold_minutes')
            ->get()
            ->groupBy(fn (SlaEscalationPolicy $policy): string => $policy->priority ?? 'default');

        Ticket::whereNotIn('status', ['closed', 'cancelled', 'rejected', 'waiting_user', 'waiting_external_party'])
            ->with(['finalPriority', 'requestedPriority'])
            ->chunkById(100, function ($tickets) use (&$stats, $policiesByPriority) {
                $ticketIds = $tickets->pluck('id')->all();

                $latestHistories = TicketStatusHistory::query()
                    ->whereIn('ticket_id', $ticketIds)
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->groupBy('ticket_id')
                    ->mapWithKeys(fn ($group, $ticketId) => [$ticketId => $group->first()]);

                foreach ($tickets as $ticket) {
                    try {
                        $stats['tickets_scanned']++;
                        $this->checkInactivity($ticket, $stats, $policiesByPriority, $latestHistories[$ticket->id] ?? null);
                    } catch (\Throwable $e) {
                        \Log::error('Inactivity scan failed for ticket '.$ticket->id, ['error' => $e->getMessage()]);
                        $stats['failures']++;
                    }
                }
            });

        return $stats;
    }

    private function checkInactivity(Ticket $ticket, array &$stats, $policiesByPriority, ?TicketStatusHistory $latestHistory): void
    {
        // For simplicity, we just use the default resolution policy's inactivity threshold,
        // or a global setting. Let's get the default response/resolution policy
        $priority = $ticket->finalPriority?->key ?? $ticket->requestedPriority?->key;
        $policy = $policiesByPriority->get($priority)?->first();

        if (! $policy) {
            $policy = $policiesByPriority->get('default')?->first();
        }

        if (! $policy || ! $policy->inactivity_threshold_minutes) {
            return;
        }

        // Use the preloaded latest status history for this ticket, falling back to updated_at.
        $lastActivity = $latestHistory ? $latestHistory->created_at : $ticket->updated_at;

        $lastActivityCarbon = Carbon::parse($lastActivity);
        $inactiveMinutes = (int) abs($lastActivityCarbon->diffInMinutes(Carbon::now()));

        if ($inactiveMinutes >= $policy->inactivity_threshold_minutes) {
            // Include ticket status in dedup key as proxy for workflow cycle
            $dedupKey = "inactivity:ticket-{$ticket->id}:status-{$ticket->status->value}:min-{$policy->inactivity_threshold_minutes}";

            $sent = $this->notificationService->dispatch(
                ticket: $ticket,
                type: 'ticket_inactive',
                severity: 'warning',
                title: 'Ticket Inactive',
                message: "Ticket {$ticket->ticket_number} has been inactive for over {$policy->inactivity_threshold_minutes} minutes.",
                actionUrl: "/tickets/{$ticket->id}",
                deduplicationRef: $dedupKey,
                deduplicationWindow: 1440 // 24 hr dedup window in minutes
            );

            if ($sent > 0) {
                $stats['alerts_sent'] += $sent;
            }
        }
    }
}
