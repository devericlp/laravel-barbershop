<?php

namespace App\Exceptions;

use App\Enums\UserRole;
use Exception;

class RoleNotEnabledException extends Exception
{
    public static function for(UserRole $role): self
    {
        return new self(__('messages.role_not_enabled_for_barbershop', ['role' => $role->label()]));
    }
}
