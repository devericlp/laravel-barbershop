<?php

namespace App\Exceptions;

use Exception;

class RoleChangeException extends Exception
{
    public static function userNeedsOneRole(): self
    {
        return new self(__('messages.user_must_keep_one_role'));
    }

    public static function lastManager(): self
    {
        return new self(__('messages.barbershop_needs_one_manager'));
    }

    public static function professionalHasUpcomingBookings(): self
    {
        return new self(__('messages.professional_has_upcoming_bookings'));
    }
}
