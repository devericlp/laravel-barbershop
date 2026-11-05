<?php

namespace Database\Seeders;

use App\Models\Professional;
use Illuminate\Database\Seeder;

class WorkingHourSeeder extends Seeder
{
    /** @param array<string, array{days: list<int>, intervals: list<array{0: string, 1: string}>}> $professionals */
    public function run(array $professionals): void
    {
        foreach ($professionals as $name => $schedule) {
            $professional = Professional::whereRelation('user', 'name', $name)->firstOrFail();

            foreach ($schedule['days'] as $day) {
                foreach ($schedule['intervals'] as [$startsAt, $endsAt]) {
                    $professional->workingHours()->create([
                        'day_of_week' => $day,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                    ]);
                }
            }
        }
    }
}
