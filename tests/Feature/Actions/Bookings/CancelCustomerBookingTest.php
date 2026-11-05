<?php

use App\Actions\Bookings\CancelCustomerBooking;
use App\Enums\BookingStatus;
use App\Exceptions\CancellationDeadlinePassedException;
use App\Models\Booking;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->booking = Booking::factory()->create(['starts_at' => CarbonImmutable::parse('2026-11-02 10:00')]);
});

test('cancels up to two hours before the start', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00'));

    app(CancelCustomerBooking::class)->handle($this->booking);

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});

test('refuses after the deadline', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 08:01'));

    app(CancelCustomerBooking::class)->handle($this->booking);
})->throws(CancellationDeadlinePassedException::class);
