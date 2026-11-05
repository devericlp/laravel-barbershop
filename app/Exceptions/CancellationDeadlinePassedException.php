<?php

namespace App\Exceptions;

use Exception;

class CancellationDeadlinePassedException extends Exception
{
    public function __construct()
    {
        parent::__construct(__('messages.cancel_only_hours_in_advance', [
            'hours' => intdiv(config()->integer('booking.cancellation_notice_minutes'), 60),
        ]));
    }
}
