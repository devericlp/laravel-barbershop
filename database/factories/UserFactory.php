<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(CurrentTenant::class)->id() ?? Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(UserRole::Manager->value));
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => null,
        ])->onlyRole(UserRole::Admin);
    }

    public function manager(): static
    {
        return $this->onlyRole(UserRole::Manager);
    }

    public function professional(): static
    {
        return $this->onlyRole(UserRole::Professional);
    }

    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => null,
        ])->onlyRole(UserRole::Customer);
    }

    public function withRole(UserRole $role): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($role->value));
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    private function onlyRole(UserRole $role): static
    {
        return $this->afterCreating(fn (User $user) => $user->syncRoles([$role->value]));
    }
}
