<?php

namespace App\Actions\Roles;

use App\Enums\UserRole;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateRole
{
    /** @param list<int> $permissionIds */
    public function handle(Role $role, string $name, array $permissionIds): Role
    {
        if ($role->name === UserRole::Admin->value) {
            throw ValidationException::withMessages(['name' => __('messages.admin_role_cannot_be_changed')]);
        }

        if ($role->is_system && $role->name !== $name) {
            throw ValidationException::withMessages(['name' => __('messages.system_role_cannot_be_renamed')]);
        }

        return DB::transaction(function () use ($role, $name, $permissionIds) {
            $role->update(['name' => $name]);
            $role->syncPermissions($permissionIds);

            return $role;
        });
    }
}
