<?php

namespace App\Actions\Professionals;

use App\Models\Professional;
use App\Models\WorkingHour;
use Illuminate\Validation\ValidationException;

final class SyncWorkingHours
{
    /** @param list<array{day_of_week: int, starts_at: string, ends_at: string}> $workingHours */
    public function handle(Professional $professional, array $workingHours): void
    {
        $this->ensureIntervalsAreValid($workingHours);

        $professional->workingHours()->delete();
        $professional->workingHours()->createMany($workingHours);
    }

    /** @param list<array{day_of_week: int, starts_at: string, ends_at: string}> $workingHours */
    private function ensureIntervalsAreValid(array $workingHours): void
    {
        $dayNames = WorkingHour::dayNames();

        foreach ($workingHours as $interval) {
            if ($interval['starts_at'] >= $interval['ends_at']) {
                throw ValidationException::withMessages([
                    'workingHours' => __('messages.interval_must_end_after_start_on_day', ['day' => $dayNames[$interval['day_of_week']]]),
                ]);
            }
        }

        foreach (collect($workingHours)->groupBy('day_of_week') as $day => $intervals) {
            $sorted = $intervals->sortBy('starts_at')->values();

            foreach ($sorted->slice(1) as $position => $interval) {
                if ($interval['starts_at'] < $sorted[$position - 1]['ends_at']) {
                    throw ValidationException::withMessages([
                        'workingHours' => __('messages.intervals_overlap_on_day', ['day' => $dayNames[$day]]),
                    ]);
                }
            }
        }
    }
}
