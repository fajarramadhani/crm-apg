<?php

namespace App\Services;

use App\Models\SlaEscalationPolicy;
use App\Models\Ticket;
use App\Models\TicketSlaAlert;
use Carbon\CarbonImmutable;

class TicketSlaEscalationService
{
    public function __construct(
        private SlaDeadlineService $deadlineService,
        private WorkingTimeCalculator $calculator,
        private TicketNotificationService $notificationService,
        private WhatsAppNotificationService $whatsAppNotificationService
    ) {}

    public function scanAndAlert(): array
    {
        $stats = [
            'tickets_scanned' => 0,
            'approaching_alerts' => 0,
            'critical_alerts' => 0,
            'breach_alerts' => 0,
            'notifications_created' => 0,
            'notifications_skipped' => 0,
            'failures' => 0,
        ];

        $escalationPolicies = SlaEscalationPolicy::query()
            ->where('is_active', true)
            ->get()
            ->groupBy(fn (SlaEscalationPolicy $policy): string => $this->policyKey($policy->sla_type, $policy->priority));

        Ticket::whereNotIn('status', ['closed', 'cancelled', 'rejected'])
            ->with(['slaPolicy.workingCalendar', 'workingCalendar', 'finalPriority', 'requestedPriority', 'currentAssignee', 'branch'])
            ->chunkById(100, function ($tickets) use (&$stats, $escalationPolicies) {
                foreach ($tickets as $ticket) {
                    try {
                        if (! $ticket->slaPolicy) {
                            continue;
                        }

                        $stats['tickets_scanned']++;
                        $this->processTicketSla($ticket, $stats, $escalationPolicies);
                    } catch (\Throwable $e) {
                        \Log::error('SLA scan failed for ticket '.$ticket->id, ['error' => $e->getMessage()]);
                        $stats['failures']++;
                    }
                }
            });

        return $stats;
    }

    private function processTicketSla(Ticket $ticket, array &$stats, $escalationPolicies): void
    {
        $policy = $ticket->slaPolicy;
        $calendar = $ticket->workingCalendar ?? $policy->workingCalendar;
        $priority = $ticket->finalPriority?->key ?? $ticket->requestedPriority?->key;

        // Check Response SLA if not responded (triage or assignment starts the clock, but simple implementation: submitted_at)
        // Usually response SLA is met when response_due_at is cleared, or just check if ticket is beyond pending_validation
        $isResponded = ! in_array($ticket->status->value, ['draft', 'pending_validation', 'validated', 'triage', 'assigned']);
        if (! $isResponded && $policy->response_minutes > 0 && $ticket->submitted_at) {
            $escalationPolicy = $this->getEscalationPolicy($escalationPolicies, $priority, 'response');
            if ($escalationPolicy) {
                $this->checkThresholds(
                    $ticket,
                    'response',
                    $escalationPolicy,
                    $calendar,
                    CarbonImmutable::instance($ticket->submitted_at),
                    $policy->response_minutes,
                    $stats
                );
            }
        }

        // Check Resolution SLA if not resolved
        $isResolved = in_array($ticket->status->value, ['ready_for_uat', 'uat_assignment', 'uat_in_progress', 'uat_approved', 'approval_pending', 'release_preparation', 'release_ready', 'deployment_scheduled', 'deployment_in_progress', 'deployed', 'monitoring', 'closed']);
        if (! $isResolved && $policy->resolution_minutes > 0 && $ticket->submitted_at) {
            $escalationPolicy = $this->getEscalationPolicy($escalationPolicies, $priority, 'resolution');
            if ($escalationPolicy) {
                $this->checkThresholds(
                    $ticket,
                    'resolution',
                    $escalationPolicy,
                    $calendar,
                    CarbonImmutable::instance($ticket->submitted_at),
                    $policy->resolution_minutes,
                    $stats
                );
            }
        }
    }

    private function getEscalationPolicy($policies, ?string $priority, string $slaType): ?SlaEscalationPolicy
    {
        if ($priority) {
            $priorityPolicy = $policies->get($this->policyKey($slaType, $priority))?->first();
            if ($priorityPolicy) {
                return $priorityPolicy;
            }
        }

        return $policies->get($this->policyKey($slaType))?->first();
    }

    private function policyKey(string $slaType, ?string $priority = null): string
    {
        return "{$slaType}:".($priority ?? 'default');
    }

    private function checkThresholds(
        Ticket $ticket,
        string $slaType,
        SlaEscalationPolicy $escalationPolicy,
        $calendar,
        CarbonImmutable $startedAt,
        int $targetMinutes,
        array &$stats
    ): void {
        $now = CarbonImmutable::now();
        $elapsedMinutes = $this->calculator->diffInWorkingMinutes($calendar, $startedAt, $now);
        $remainingMinutes = $targetMinutes - $elapsedMinutes;

        $percentage = ($targetMinutes > 0) ? (int) (($elapsedMinutes / $targetMinutes) * 100) : 100;

        $alertLevel = null;
        if ($percentage >= 100) {
            $alertLevel = 'breached';
        } elseif ($percentage >= $escalationPolicy->critical_threshold_percent) {
            $alertLevel = 'critical';
        } elseif ($percentage >= $escalationPolicy->warning_threshold_percent) {
            $alertLevel = 'approaching';
        }

        if (! $alertLevel) {
            return;
        }

        // Create deduplication key for this alert
        $dedupKey = "sla_{$alertLevel}:{$slaType}:ticket-{$ticket->id}";

        // Ensure we don't alert the same level/threshold twice
        if (TicketSlaAlert::where('deduplication_key', $dedupKey)->exists()) {
            return;
        }

        TicketSlaAlert::create([
            'ticket_id' => $ticket->id,
            'ticket_sla_id' => $ticket->id, // Fallback since no ticket_sla table
            'sla_type' => $slaType,
            'alert_level' => $alertLevel,
            'threshold_percent' => $percentage,
            'elapsed_minutes' => $elapsedMinutes,
            'target_minutes' => $targetMinutes,
            'remaining_minutes' => $remainingMinutes,
            'recipient_scope' => $this->getRecipientScope($escalationPolicy),
            'triggered_at' => $now,
            'deduplication_key' => $dedupKey,
        ]);

        if ($alertLevel === 'breached') {
            $stats['breach_alerts']++;
        } elseif ($alertLevel === 'critical') {
            $stats['critical_alerts']++;
        } else {
            $stats['approaching_alerts']++;
        }

        // Trigger Notification
        $this->notifyAlert($ticket, $slaType, $alertLevel, $remainingMinutes, $dedupKey, $stats);
    }

    private function getRecipientScope(SlaEscalationPolicy $policy): string
    {
        $scopes = [];
        if ($policy->escalate_to_supervisor) {
            $scopes[] = 'supervisor';
        }
        if ($policy->escalate_to_it_lead) {
            $scopes[] = 'it_lead';
        }
        if ($policy->escalate_to_manager) {
            $scopes[] = 'manager';
        }

        return implode(',', $scopes);
    }

    private function notifyAlert(Ticket $ticket, string $slaType, string $alertLevel, int $remainingMinutes, string $dedupKey, array &$stats): void
    {
        $type = $alertLevel === 'breached' ? 'sla_breached' : 'sla_approaching';
        $severity = $alertLevel === 'approaching' ? 'warning' : 'critical';
        $title = "SLA {$alertLevel} - ".ucfirst($slaType);

        $msg = $alertLevel === 'breached'
            ? "Ticket {$ticket->ticket_number} has breached its {$slaType} SLA by ".abs($remainingMinutes).' minutes.'
            : "Ticket {$ticket->ticket_number} is approaching its {$slaType} SLA. {$remainingMinutes} minutes remaining.";

        // In a real app we'd dispatch this async or handled within the service.
        // We'll pass deduplication info to avoid multiple notifications for same scan if retried.
        $this->notificationService->dispatch(
            ticket: $ticket,
            type: $type,
            severity: $severity,
            title: $title,
            message: $msg,
            actionUrl: "/tickets/{$ticket->id}",
            metadata: ['sla_type' => $slaType, 'alert_level' => $alertLevel],
            deduplicationRef: $dedupKey,
            deduplicationWindow: 60 // 1 hour window
        );

        $ticket->loadMissing(['branch:id,name', 'currentAssignee:id,name,phone']);
        $branch = $ticket->branch?->name ?? 'Kantor Pusat';
        $baseUrl = rtrim(trim(explode(',', (string) config('public_tracking.frontend_url', config('app.url')))[0]), '/');
        $path = $ticket->current_assignee_id ? "pic/tickets/{$ticket->id}" : "supervisor-it/tickets/{$ticket->id}";
        $internalUrl = "{$baseUrl}/{$path}";
        $waEventType = $alertLevel === 'breached' ? 'sla_breached' : 'sla_warning';

        $variables = [
            'ticket_number' => $ticket->ticket_number,
            'branch' => $branch,
            'sla_type' => strtoupper($slaType),
            'status' => $alertLevel === 'breached' ? 'Terlewati' : 'Mendekati batas',
            'minutes' => (string) abs($remainingMinutes),
            'internal_url' => $internalUrl,
        ];
        if (WhatsAppNotificationService::normalizePhoneNumber($ticket->currentAssignee?->phone)) {
            $this->whatsAppNotificationService->queueSafely($waEventType, $ticket, $ticket->currentAssignee->phone,
                'pic', 'user', 'sla_recipient', $variables, "wa-{$dedupKey}:pic-{$ticket->current_assignee_id}");
        } else {
            $this->whatsAppNotificationService->notifyItSupport($waEventType, $ticket, $variables, "wa-{$dedupKey}:it-support");
        }

        $stats['notifications_created']++;
    }
}
