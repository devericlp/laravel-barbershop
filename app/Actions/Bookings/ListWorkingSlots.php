<?php

namespace App\Actions\Bookings;

use App\Models\Professional;
use Carbon\CarbonImmutable;

final class ListWorkingSlots
{
    /** @return list<CarbonImmutable> */
    public function handle(Professional $professional, int $durationMinutes, CarbonImmutable $date): array
    {
        $day = $date->startOfDay();
        $step = config()->integer('booking.slot_interval_minutes');
        $slots = [];

        $intervals = $professional->workingHours()
            ->where('day_of_week', $day->dayOfWeek)
            ->orderBy('starts_at')
            ->get();

        foreach ($intervals as $interval) {
            $intervalEnd = $day->setTimeFromTimeString($interval->ends_at);

            for (
                $start = $day->setTimeFromTimeString($interval->starts_at);
                $start->addMinutes($durationMinutes) <= $intervalEnd;
                $start = $start->addMinutes($step)
            ) {
                $slots[] = $start;
            }
        }

        return $slots;
    }
}
