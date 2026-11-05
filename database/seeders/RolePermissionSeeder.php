<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public const array PERMISSIONS = [
        'dashboard.index' => [UserRole::Manager],
        'bookings.index' => [UserRole::Manager, UserRole::Professional],
        'bookings.create' => [UserRole::Manager],
        'bookings.update' => [UserRole::Manager],
        'bookings.cancel' => [UserRole::Manager],
        'bookings.complete' => [UserRole::Manager, UserRole::Professional],
        'bookings.no-show' => [UserRole::Manager, UserRole::Professional],
        'services.index' => [UserRole::Manager],
        'services.create' => [UserRole::Manager],
        'services.update' => [UserRole::Manager],
        'services.destroy' => [UserRole::Manager],
        'services.toggle-status' => [UserRole::Manager],
        'professionals.index' => [UserRole::Manager],
        'professionals.create' => [UserRole::Manager],
        'professionals.update' => [UserRole::Manager],
        'professionals.destroy' => [UserRole::Manager],
        'professionals.toggle-status' => [UserRole::Manager],
        'customers.index' => [UserRole::Manager],
        'customers.create' => [UserRole::Manager],
        'customers.update' => [UserRole::Manager],
        'customers.destroy' => [UserRole::Manager],
        'users.index' => [UserRole::Manager],
        'users.create' => [UserRole::Manager],
        'users.update' => [UserRole::Manager],
        'users.destroy' => [UserRole::Manager],
        'users.link-customer' => [UserRole::Manager],
        'my-bookings.index' => [UserRole::Customer],
        'my-bookings.create' => [UserRole::Customer],
        'my-bookings.cancel' => [UserRole::Customer],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = collect(UserRole::cases())->mapWithKeys(fn (UserRole $role) => [
            $role->value => Role::firstOrCreate(
                ['name' => $role->value, 'guard_name' => 'web'],
                ['is_system' => true],
            ),
        ]);

        foreach (self::PERMISSIONS as $name => $holders) {
            $permission = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['is_system' => true],
            );

            foreach ($holders as $holder) {
                $roles[$holder->value]->givePermissionTo($permission);
            }
        }
    }
}
