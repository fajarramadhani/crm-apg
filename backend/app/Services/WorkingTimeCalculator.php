<?php

namespace App\Services;

use App\Models\WorkingCalendar;
use Carbon\CarbonImmutable;

final class WorkingTimeCalculator
{
    public function addWorkingMinutes(WorkingCalendar $calendar, CarbonImmutable $startedAt, int $minutes): CarbonImmutable
    {
        $timezone = $calendar->timezone;
        $cursor = $this->nextWorkingInstant($calendar, $startedAt->setTimezone($timezone));
        $remaining = $minutes;

        while ($remaining > 0) {
            $end = $cursor->setTimeFromTimeString($calendar->workday_end);
            $available = (int) $cursor->diffInMinutes($end, true);
            if ($remaining <= $available) {
                return $cursor->addMinutes($remaining);
            }
            $remaining -= $available;
            $cursor = $this->nextWorkingInstant($calendar, $cursor->addDay()->startOfDay());
        }

        return $cursor;
    }

    public function nextWorkingInstant(WorkingCalendar $calendar, CarbonImmutable $instant): CarbonImmutable
    {
        $cursor = $instant->setTimezone($calendar->timezone);
        for ($guard = 0; $guard < 3700; $guard++) {
            if (! $this->isWorkingDate($calendar, $cursor)) {
                $cursor = $cursor->addDay()->startOfDay();

                continue;
            }
            $start = $cursor->setTimeFromTimeString($calendar->workday_start);
            $end = $cursor->setTimeFromTimeString($calendar->workday_end);
            if ($cursor->lessThan($start)) {
                return $start;
            }
            if ($cursor->lessThan($end)) {
                return $cursor;
            }
            $cursor = $cursor->addDay()->startOfDay();
        }
        throw new \RuntimeException('No working date could be resolved for the calendar.');
    }

    private function isWorkingDate(WorkingCalendar $calendar, CarbonImmutable $date): bool
    {
        if (! in_array($date->dayOfWeekIso, array_map('intval', $calendar->working_days), true)) {
            return false;
        }

        return ! $calendar->holidays()->whereDate('date', $date->toDateString())->exists();
    }
}
