<?php

namespace App\Actions\Roles;

use App\Models\Role;
use Illuminate\Support\Facades\DB;

final class CreateRole
{
    /** @param list<int> $permissionIds */
    public function handle(string $name, array $permissionIds): Role
    {
        return DB::transaction(function () use ($name, $permissionIds) {
            $role = new Role(['name' => $name, 'is_system' => false]);
            $role->save();
            $role->syncPermissions($permissionIds);

            return $role;
        });
    }
}
