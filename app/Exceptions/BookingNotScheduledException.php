<?php

namespace App\Exceptions;

use Exception;

class BookingNotScheduledException extends Exception
{
    public function __construct()
    {
        parent::__construct(__('messages.only_scheduled_bookings_can_change'));
    }
}
