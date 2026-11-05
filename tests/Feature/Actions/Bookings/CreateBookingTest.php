<?php

use App\Actions\Bookings\CreateBooking;
use App\Data\BookingData;
use App\Enums\BookingChannel;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 07:00'));
    $this->service = Service::factory()->create(['price_cents' => 4500, 'duration_minutes' => 30]);
    $this->professional = bookableProfessional($this->service);
    $this->customer = Customer::factory()->create();
});

function bookingData(int $professionalId, int $serviceId, string $startsAt, ?int $customerId = null): BookingData
{
    return new BookingData($customerId ?? test()->customer->id, $professionalId, $serviceId, CarbonImmutable::parse($startsAt));
}

test('creates a scheduled booking copying price and duration', function () {
    $booking = app(CreateBooking::class)->handle(
        bookingData($this->professional->id, $this->service->id, '2026-11-02 09:30'),
        BookingChannel::Public,
    );

    expect($booking->status)->toBe(BookingStatus::Scheduled)
        ->and($booking->price_cents)->toBe(4500)
        ->and($booking->duration_minutes)->toBe(30)
        ->and($booking->ends_at->format('H:i'))->toBe('10:00')
        ->and($booking->customer_id)->toBe($this->customer->id)
        ->and($booking->tenant_id)->toBe($this->tenant->id);
});

test('rejects a professional booking with themselves', function () {
    $customer = Customer::factory()->create(['user_id' => $this->professional->user_id]);

    app(CreateBooking::class)->handle(
        bookingData($this->professional->id, $this->service->id, '2026-11-02 09:30', $customer->id),
        BookingChannel::Admin,
    );
})->throws(ValidationException::class, 'O profissional não pode agendar um atendimento consigo mesmo.');

test('rejects a customer from another tenant', function () {
    $outsider = inTenant(Tenant::factory()->create(), fn () => Customer::factory()->create());

    app(CreateBooking::class)->handle(
        bookingData($this->professional->id, $this->service->id, '2026-11-02 09:30', $outsider->id),
        BookingChannel::Public,
    );
})->throws(ModelNotFoundException::class);

test('rejects an occupied slot', function () {
    Booking::factory()->for($this->professional)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 09:15')]);

    app(CreateBooking::class)->handle(
        bookingData($this->professional->id, $this->service->id, '2026-11-02 09:30'),
        BookingChannel::Public,
    );
})->throws(ValidationException::class);

test('rejects a slot outside working hours', function () {
    app(CreateBooking::class)->handle(
        bookingData($this->professional->id, $this->service->id, '2026-11-02 11:45'),
        BookingChannel::Public,
    );
})->throws(ValidationException::class);

test('rejects a public slot inside the minimum notice', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 09:10'));

    app(CreateBooking::class)->handle(
        bookingData($this->professional->id, $this->service->id, '2026-11-02 09:30'),
        BookingChannel::Public,
    );
})->throws(ValidationException::class);
