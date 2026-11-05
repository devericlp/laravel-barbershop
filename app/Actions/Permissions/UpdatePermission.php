<?php

namespace App\Actions\Permissions;

use App\Models\Permission;
use Illuminate\Validation\ValidationException;

final class UpdatePermission
{
    public function handle(Permission $permission, string $name): Permission
    {
        if ($permission->is_system) {
            throw ValidationException::withMessages(['name' => __('messages.system_permission_cannot_be_renamed')]);
        }

        $permission->update(['name' => $name]);

        return $permission;
    }
}
