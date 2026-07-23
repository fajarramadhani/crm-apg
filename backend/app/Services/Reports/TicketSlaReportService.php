<?php

namespace App\Services\Reports;

use App\Models\Ticket;
use Carbon\Carbon;

class TicketSlaReportService extends BaseReportService
{
    public function getSummary(array $filters, Carbon $dateFrom, Carbon $dateTo): array
    {
        $query = Ticket::query()->whereNotNull('sla_policy_id');
        $query = $this->applyFilters($query, $filters, 'submitted_at', $dateFrom, $dateTo);

        $tickets = $query->get(['id', 'submitted_at', 'response_due_at', 'resolution_due_at', 'triage_started_at', 'closed_at']);

        $totalSlaTickets = $tickets->count();
        $responseEligible = 0;
        $responseMet = 0;
        $resolutionEligible = 0;
        $resolutionMet = 0;

        $responseTimes = [];
        $resolutionTimes = [];

        foreach ($tickets as $ticket) {
            // Response SLA
            if ($ticket->triage_started_at) {
                $responseEligible++;
                if ($ticket->response_due_at && $ticket->triage_started_at->lte($ticket->response_due_at)) {
                    $responseMet++;
                }
                $responseTimes[] = $ticket->triage_started_at->diffInMinutes($ticket->submitted_at);
            } elseif ($ticket->response_due_at && now()->gt($ticket->response_due_at)) {
                $responseEligible++; // Breached, not met
            }

            // Resolution SLA
            if ($ticket->closed_at) {
                $resolutionEligible++;
                if ($ticket->resolution_due_at && $ticket->closed_at->lte($ticket->resolution_due_at)) {
                    $resolutionMet++;
                }
                $resolutionTimes[] = $ticket->closed_at->diffInMinutes($ticket->submitted_at);
            } elseif ($ticket->resolution_due_at && now()->gt($ticket->resolution_due_at)) {
                $resolutionEligible++; // Breached, not met
            }
        }

        $noSlaQuery = Ticket::query()->whereNull('sla_policy_id');
        $noSlaQuery = $this->applyFilters($noSlaQuery, $filters, 'submitted_at', $dateFrom, $dateTo);
        $ticketsWithoutSla = $noSlaQuery->count();

        $overallEligible = $responseEligible + $resolutionEligible;
        $overallMet = $responseMet + $resolutionMet;

        return [
            'total_tickets_with_sla' => $totalSlaTickets,
            'tickets_without_sla' => $ticketsWithoutSla,
            'compliance_percentage' => $overallEligible > 0 ? round(($overallMet / $overallEligible) * 100, 2) : null,
            'response_compliance_percentage' => $responseEligible > 0 ? round(($responseMet / $responseEligible) * 100, 2) : null,
            'resolution_compliance_percentage' => $resolutionEligible > 0 ? round(($resolutionMet / $resolutionEligible) * 100, 2) : null,
            'response_eligible' => $responseEligible,
            'response_met' => $responseMet,
            'response_breached' => $responseEligible - $responseMet,
            'resolution_eligible' => $resolutionEligible,
            'resolution_met' => $resolutionMet,
            'resolution_breached' => $resolutionEligible - $resolutionMet,
            'average_response_time_minutes' => count($responseTimes) > 0 ? round(array_sum($responseTimes) / count($responseTimes), 2) : null,
            'median_response_time_minutes' => $this->calculateMedian($responseTimes),
            'average_resolution_time_minutes' => count($resolutionTimes) > 0 ? round(array_sum($resolutionTimes) / count($resolutionTimes), 2) : null,
            'median_resolution_time_minutes' => $this->calculateMedian($resolutionTimes),
        ];
    }
}
