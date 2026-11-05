<?php

namespace App\Exceptions;

use Exception;

class CannotDeleteRecordException extends Exception
{
    public static function serviceHasBookings(): self
    {
        return new self(__('messages.service_has_bookings_cannot_delete'));
    }

    public static function professionalHasBookings(): self
    {
        return new self(__('messages.professional_has_bookings_cannot_delete'));
    }

    public static function customerHasBookings(): self
    {
        return new self(__('messages.customer_has_bookings_cannot_delete'));
    }

    public static function customerHasAccount(): self
    {
        return new self(__('messages.customer_linked_to_account_cannot_delete'));
    }

    public static function ownAccount(): self
    {
        return new self(__('messages.cannot_delete_own_account'));
    }

    public static function lastManager(): self
    {
        return new self(__('messages.barbershop_needs_one_manager'));
    }

    public static function systemRole(): self
    {
        return new self(__('messages.system_role_cannot_be_deleted'));
    }

    public static function roleHasUsers(): self
    {
        return new self(__('messages.role_has_users_cannot_delete'));
    }

    public static function systemPermission(): self
    {
        return new self(__('messages.system_permission_cannot_be_deleted'));
    }
}
