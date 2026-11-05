<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(CurrentTenant::class)->id() ?? Tenant::factory(),
            'name' => fake()->unique()->words(2, true),
            'price_cents' => fake()->randomElement([2000, 3500, 4500, 7000]),
            'duration_minutes' => 30,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
