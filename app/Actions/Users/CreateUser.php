<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Exceptions\RoleNotEnabledException;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;

final class CreateUser
{
    public function __construct(
        private CurrentTenant $currentTenant,
    ) {}

    public function handle(UserRole $role, string $name, string $email, string $password, ?Tenant $tenant = null): User
    {
        $tenant ??= $this->currentTenant->get();

        if ($tenant !== null && ! $tenant->roles()->where('name', $role->value)->exists()) {
            throw RoleNotEnabledException::for($role);
        }

        return DB::transaction(function () use ($role, $name, $email, $password, $tenant) {
            $attributes = [
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ];

            $user = $tenant !== null && $role !== UserRole::Customer
                ? $tenant->users()->create($attributes)
                : User::forceCreate([...$attributes, 'tenant_id' => null]);

            $user->assignRole($role->value);

            return $user;
        });
    }
}
