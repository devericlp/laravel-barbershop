<?php

namespace App\Actions\Roles;

use App\Exceptions\CannotDeleteRecordException;
use App\Models\Role;
use App\Models\User;

final class DeleteRole
{
    public function handle(Role $role): void
    {
        if ($role->is_system) {
            throw CannotDeleteRecordException::systemRole();
        }

        if (User::withoutGlobalScopes()->role($role)->exists()) {
            throw CannotDeleteRecordException::roleHasUsers();
        }

        $role->delete();
    }
}
