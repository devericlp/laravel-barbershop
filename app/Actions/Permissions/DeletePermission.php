<?php

namespace App\Actions\Permissions;

use App\Exceptions\CannotDeleteRecordException;
use App\Models\Permission;

final class DeletePermission
{
    public function handle(Permission $permission): void
    {
        if ($permission->is_system) {
            throw CannotDeleteRecordException::systemPermission();
        }

        $permission->delete();
    }
}
