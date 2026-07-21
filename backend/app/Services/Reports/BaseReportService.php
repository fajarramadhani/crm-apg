<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class BaseReportService
{
    protected function applyFilters(Builder $query, array $filters, string $dateColumn = 'created_at', ?Carbon $dateFrom = null, ?Carbon $dateTo = null): Builder
    {
        if ($dateFrom) {
            $query->where($dateColumn, '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where($dateColumn, '<=', $dateTo);
        }

        if (isset($filters['division_id'])) {
            $query->where('division_id', $filters['division_id']);
        }

        if (isset($filters['application_id'])) {
            $query->where('application_id', $filters['application_id']);
        }

        if (isset($filters['category_id'])) {
            $query->where('ticket_category_id', $filters['category_id']);
        }

        if (isset($filters['priority'])) {
            $query->where('final_priority_id', $filters['priority']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['pic_id'])) {
            $query->where('current_assignee_id', $filters['pic_id']);
        }

        if (isset($filters['requester_id'])) {
            $query->where('requester_id', $filters['requester_id']);
        }

        return $query;
    }

    protected function calculateMedian(array $values): ?float
    {
        $count = count($values);
        if ($count === 0) {
            return null;
        }

        sort($values);
        $middle = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2.0;
        }

        return (float) $values[$middle];
    }
}
