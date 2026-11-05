<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\BookingNotScheduledException;
use App\Models\Booking;

final class CompleteBooking
{
    public function handle(Booking $booking): Booking
    {
        if (! $booking->isScheduled()) {
            throw new BookingNotScheduledException;
        }

        $booking->update(['status' => BookingStatus::Completed]);

        return $booking;
    }
}
