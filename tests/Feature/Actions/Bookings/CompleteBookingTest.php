<?php

use App\Actions\Bookings\CompleteBooking;
use App\Enums\BookingStatus;
use App\Exceptions\BookingNotScheduledException;
use App\Models\Booking;

test('completes a scheduled booking', function () {
    $booking = app(CompleteBooking::class)->handle(Booking::factory()->create());

    expect($booking->fresh()->status)->toBe(BookingStatus::Completed);
});

test('rejects a booking that is not scheduled', function () {
    app(CompleteBooking::class)->handle(Booking::factory()->cancelled()->create());
})->throws(BookingNotScheduledException::class);
