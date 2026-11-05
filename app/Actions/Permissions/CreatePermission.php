<?php

namespace App\Actions\Permissions;

use App\Models\Permission;

final class CreatePermission
{
    public function handle(string $name): Permission
    {
        $permission = new Permission(['name' => $name, 'is_system' => false]);
        $permission->save();

        return $permission;
    }
}
