<?php

use App\Actions\Bookings\CreateGuestBooking;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 07:00'));
    $this->service = Service::factory()->create(['price_cents' => 4500, 'duration_minutes' => 30]);
    $this->professional = bookableProfessional($this->service);
});

test('creates a customer record without account for a new phone', function () {
    $booking = app(CreateGuestBooking::class)->handle(
        'Ana Souza', '(11) 98888-7777', $this->professional->id, $this->service->id, CarbonImmutable::parse('2026-11-02 09:30'),
    );

    $customer = Customer::sole();

    expect($booking->customer_id)->toBe($customer->id)
        ->and($customer->name)->toBe('Ana Souza')
        ->and($customer->phone)->toBe('11988887777')
        ->and($customer->user_id)->toBeNull()
        ->and($customer->tenant_id)->toBe($this->tenant->id);
});

test('reuses the customer record that already has the phone and keeps its name', function () {
    $existing = Customer::factory()->named('João Silva')->create(['phone' => '11988887777']);

    $booking = app(CreateGuestBooking::class)->handle(
        'Outro Nome', '11 98888-7777', $this->professional->id, $this->service->id, CarbonImmutable::parse('2026-11-02 09:30'),
    );

    expect($booking->customer_id)->toBe($existing->id)
        ->and(Customer::count())->toBe(1)
        ->and($existing->fresh()->name)->toBe('João Silva');
});

test('does not reuse a customer record from another barbershop', function () {
    $other = Tenant::factory()->create();
    inTenant($other, fn () => Customer::factory()->create(['phone' => '11988887777']));

    $booking = app(CreateGuestBooking::class)->handle(
        'Ana Souza', '11988887777', $this->professional->id, $this->service->id, CarbonImmutable::parse('2026-11-02 09:30'),
    );

    expect($booking->customer->tenant_id)->toBe($this->tenant->id)
        ->and($booking->customer->name)->toBe('Ana Souza');
});

test('creates nothing when the time is no longer available', function () {
    Booking::factory()->for($this->professional)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 09:30')]);
    $customers = Customer::count();

    expect(fn () => app(CreateGuestBooking::class)->handle(
        'Ana Souza', '11988887777', $this->professional->id, $this->service->id, CarbonImmutable::parse('2026-11-02 09:30'),
    ))->toThrow(ValidationException::class);

    expect(Customer::count())->toBe($customers)
        ->and(Booking::count())->toBe(1);
});
