<?php

namespace App\Services\Reports;

use App\Models\TicketStatusHistory;
use Carbon\Carbon;

class TicketWorkflowDurationReportService extends BaseReportService
{
    public function getSummary(array $filters, Carbon $dateFrom, Carbon $dateTo): array
    {
        $query = TicketStatusHistory::query()
            ->select('ticket_id', 'to_status', 'created_at', 'from_status')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereIn('ticket_id', function ($q) use ($filters) {
                $q->select('id')->from('tickets');
                if (isset($filters['division_id'])) {
                    $q->where('division_id', $filters['division_id']);
                }
                if (isset($filters['application_id'])) {
                    $q->where('application_id', $filters['application_id']);
                }
            })
            ->orderBy('ticket_id')
            ->orderBy('created_at');

        $histories = $query->get();

        $durations = [];
        $ticketState = [];

        foreach ($histories as $history) {
            $ticketId = $history->ticket_id;

            if (! isset($ticketState[$ticketId])) {
                $ticketState[$ticketId] = [
                    'status' => $history->to_status,
                    'since' => $history->created_at,
                ];

                continue;
            }

            $prevState = $ticketState[$ticketId];
            $prevStatus = $prevState['status'];
            $timeInState = $prevState['since']->diffInMinutes($history->created_at);

            if (! isset($durations[$prevStatus])) {
                $durations[$prevStatus] = [];
            }
            $durations[$prevStatus][] = $timeInState;

            $ticketState[$ticketId] = [
                'status' => $history->to_status,
                'since' => $history->created_at,
            ];
        }

        $result = [];
        $phases = [
            'draft' => 'Requester Submission',
            'pending_validation' => 'Supervisor Validation',
            'pending_triage' => 'IT Lead Triage',
            'pending_assignment' => 'PIC Assignment',
            'in_analysis' => 'Analysis',
            'in_solution_planning' => 'Solution Planning',
            'in_development' => 'Development',
            'in_internal_testing' => 'Internal Testing',
            'ready_for_qa' => 'Ready for QA',
            'in_qa_testing' => 'QA Testing',
            'ready_for_uat' => 'Ready for UAT',
            'in_uat_testing' => 'UAT Testing',
            'pending_business_approval' => 'Business Approval',
            'pending_technical_approval' => 'Technical Approval',
            'pending_release_preparation' => 'Release Preparation',
            'ready_for_deployment' => 'Ready for Deployment',
            'in_deployment' => 'Deployment',
            'in_monitoring' => 'Monitoring',
            'pending_requester_confirmation' => 'Requester Confirmation',
        ];

        foreach ($phases as $statusKey => $label) {
            $values = $durations[$statusKey] ?? [];
            if (count($values) > 0) {
                sort($values);
                $count = count($values);
                $sum = array_sum($values);

                $result[$statusKey] = [
                    'label' => $label,
                    'average_minutes' => round($sum / $count, 2),
                    'median_minutes' => $this->calculateMedian($values),
                    'min_minutes' => $values[0],
                    'max_minutes' => $values[$count - 1],
                    'p75_minutes' => $values[(int) floor($count * 0.75)],
                    'p90_minutes' => $values[(int) floor($count * 0.90)],
                    'ticket_count' => $count,
                ];
            } else {
                $result[$statusKey] = [
                    'label' => $label,
                    'average_minutes' => null,
                    'median_minutes' => null,
                    'min_minutes' => null,
                    'max_minutes' => null,
                    'p75_minutes' => null,
                    'p90_minutes' => null,
                    'ticket_count' => 0,
                ];
            }
        }

        return $result;
    }
}
