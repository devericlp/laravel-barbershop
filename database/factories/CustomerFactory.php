<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(CurrentTenant::class)->id() ?? Tenant::factory(),
            'user_id' => fn () => User::factory()->customer(),
            'name' => fn (array $attributes) => $attributes['user_id'] !== null
                ? User::withoutGlobalScopes()->whereKey($attributes['user_id'])->firstOrFail()->name
                : fake()->name(),
            'phone' => fake()->unique()->numerify('119########'),
        ];
    }

    public function named(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => fn () => User::factory()->customer()->create(['name' => $name]),
            'name' => $name,
        ]);
    }

    public function withoutUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }
}
