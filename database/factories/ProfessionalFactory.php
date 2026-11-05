<?php

namespace Database\Factories;

use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Professional>
 */
class ProfessionalFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(CurrentTenant::class)->id() ?? Tenant::factory(),
            'user_id' => fn (array $attributes) => User::factory()->professional()->create([
                'tenant_id' => $attributes['tenant_id'],
                'name' => fake()->name('male'),
            ]),
            'photo_path' => null,
            'is_active' => true,
        ];
    }

    public function named(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => fn (array $attributes) => User::factory()->professional()->create([
                'tenant_id' => $attributes['tenant_id'],
                'name' => $name,
            ]),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
