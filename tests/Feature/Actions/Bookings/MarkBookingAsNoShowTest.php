<?php

use App\Actions\Bookings\MarkBookingAsNoShow;
use App\Enums\BookingStatus;
use App\Exceptions\BookingNotScheduledException;
use App\Models\Booking;

test('marks a scheduled booking as no show', function () {
    $booking = app(MarkBookingAsNoShow::class)->handle(Booking::factory()->create());

    expect($booking->fresh()->status)->toBe(BookingStatus::NoShow);
});

test('rejects a booking that is not scheduled', function () {
    app(MarkBookingAsNoShow::class)->handle(Booking::factory()->noShow()->create());
})->throws(BookingNotScheduledException::class);
