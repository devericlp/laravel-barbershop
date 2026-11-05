<?php

namespace App\Actions\Bookings;

use App\Exceptions\CancellationDeadlinePassedException;
use App\Models\Booking;
use Carbon\CarbonImmutable;

final class CancelCustomerBooking
{
    public function __construct(
        private CancelBooking $cancelBooking,
    ) {}

    public function handle(Booking $booking): Booking
    {
        $deadline = $booking->starts_at->subMinutes(config()->integer('booking.cancellation_notice_minutes'));

        if (CarbonImmutable::now()->isAfter($deadline)) {
            throw new CancellationDeadlinePassedException;
        }

        return $this->cancelBooking->handle($booking);
    }
}
