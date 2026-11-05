<?php

namespace Database\Factories;

use App\Models\Professional;
use App\Models\WorkingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkingHour>
 */
class WorkingHourFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'professional_id' => Professional::factory(),
            'day_of_week' => fake()->numberBetween(1, 6),
            'starts_at' => '09:00',
            'ends_at' => '18:00',
        ];
    }
}
