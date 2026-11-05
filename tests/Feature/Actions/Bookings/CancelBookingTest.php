<?php

use App\Actions\Bookings\CancelBooking;
use App\Enums\BookingStatus;
use App\Exceptions\BookingNotScheduledException;
use App\Models\Booking;

test('cancels a scheduled booking and keeps the record', function () {
    $booking = app(CancelBooking::class)->handle(Booking::factory()->create());

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and(Booking::count())->toBe(1);
});

test('rejects a booking that is not scheduled', function () {
    app(CancelBooking::class)->handle(Booking::factory()->completed()->create());
})->throws(BookingNotScheduledException::class);
