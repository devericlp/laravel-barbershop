<?php

use App\Actions\Bookings\UpdateBooking;
use App\Data\BookingData;
use App\Exceptions\BookingNotScheduledException;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 07:00'));
    $this->service = Service::factory()->create(['price_cents' => 4500, 'duration_minutes' => 30]);
    $this->professional = bookableProfessional($this->service);
    $this->booking = Booking::factory()->for($this->professional)->for($this->service)->create([
        'starts_at' => CarbonImmutable::parse('2026-11-02 09:00'),
        'price_cents' => 4000,
        'duration_minutes' => 30,
    ]);
    $this->customer = Customer::factory()->named('Maria Lima')->create();
});

function updateData(int $professionalId, int $serviceId, string $startsAt): BookingData
{
    return new BookingData(test()->customer->id, $professionalId, $serviceId, CarbonImmutable::parse($startsAt), 'Chega atrasada');
}

test('moves the booking keeping the copied price when the service stays', function () {
    $booking = app(UpdateBooking::class)->handle($this->booking, updateData($this->professional->id, $this->service->id, '2026-11-02 09:15'));

    expect($booking->starts_at->format('H:i'))->toBe('09:15')
        ->and($booking->ends_at->format('H:i'))->toBe('09:45')
        ->and($booking->price_cents)->toBe(4000)
        ->and($booking->notes)->toBe('Chega atrasada')
        ->and($booking->customer->name)->toBe('Maria Lima');
});

test('recopies price and duration when the service changes', function () {
    $beard = Service::factory()->create(['price_cents' => 7000, 'duration_minutes' => 60]);
    $this->professional->services()->attach($beard);

    $booking = app(UpdateBooking::class)->handle($this->booking, updateData($this->professional->id, $beard->id, '2026-11-02 10:00'));

    expect($booking->price_cents)->toBe(7000)
        ->and($booking->duration_minutes)->toBe(60)
        ->and($booking->ends_at->format('H:i'))->toBe('11:00');
});

test('rejects a slot taken by another booking', function () {
    Booking::factory()->for($this->professional)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 10:00')]);

    app(UpdateBooking::class)->handle($this->booking, updateData($this->professional->id, $this->service->id, '2026-11-02 09:45'));
})->throws(ValidationException::class);

test('keeps an unchanged slot even after its time has passed', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 11:00'));

    $booking = app(UpdateBooking::class)->handle($this->booking, updateData($this->professional->id, $this->service->id, '2026-11-02 09:00'));

    expect($booking->notes)->toBe('Chega atrasada');
});

test('only scheduled bookings can be edited', function () {
    $this->booking->update(['status' => 'completed']);

    app(UpdateBooking::class)->handle($this->booking, updateData($this->professional->id, $this->service->id, '2026-11-02 09:15'));
})->throws(BookingNotScheduledException::class);

test('rejects moving a booking to the professional who is the customer', function () {
    $this->customer = Customer::factory()->create(['user_id' => $this->professional->user_id]);

    app(UpdateBooking::class)->handle($this->booking, updateData($this->professional->id, $this->service->id, '2026-11-02 09:00'));
})->throws(ValidationException::class);
