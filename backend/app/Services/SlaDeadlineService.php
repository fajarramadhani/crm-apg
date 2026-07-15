<?php

namespace App\Services;

use App\Models\SlaPolicy;
use Carbon\CarbonImmutable;

final class SlaDeadlineService
{
    public function __construct(private WorkingTimeCalculator $calculator) {}

    public function calculate(SlaPolicy $policy, CarbonImmutable $startedAt): array
    {
        $calendar = $policy->workingCalendar;

        return [
            'response_due_at' => $policy->response_minutes ? $this->calculator->addWorkingMinutes($calendar, $startedAt, $policy->response_minutes) : null,
            'resolution_due_at' => $this->calculator->addWorkingMinutes($calendar, $startedAt, $policy->resolution_minutes),
            'timezone' => $calendar->timezone,
        ];
    }
}
