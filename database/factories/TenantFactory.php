<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Barbearia '.fake()->unique()->lastName(),
            'slug' => fn (array $attributes) => Tenant::uniqueSlugFor($attributes['name']),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Tenant $tenant) => $tenant->roles()->attach(
            Role::assignable()->where('is_system', true)->pluck('id'),
        ));
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
