<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 07:00'));
    $this->customer = Customer::factory()->create();
    $this->actingAs($this->customer->user);
});

test('lists only the customer bookings split into upcoming and past', function () {
    Booking::factory()->for($this->customer)->for(Service::factory()->create(['name' => 'Corte futuro']))
        ->create(['starts_at' => CarbonImmutable::parse('2026-11-03 10:00')]);
    Booking::factory()->for($this->customer)->for(Service::factory()->create(['name' => 'Corte passado']))->completed()
        ->create(['starts_at' => CarbonImmutable::parse('2026-10-30 10:00')]);
    Booking::factory()->for(Service::factory()->create(['name' => 'Corte de outro cliente']))
        ->create(['starts_at' => CarbonImmutable::parse('2026-11-03 11:00')]);

    $this->get(route('my-bookings.index'))
        ->assertOk()
        ->assertSee('Corte futuro')
        ->assertSee('Corte passado')
        ->assertDontSee('Corte de outro cliente');

    Livewire::test('pages::my-bookings.my-booking-index')
        ->assertCount('upcoming', 1)
        ->assertCount('past', 1);
});

test('cancels an own booking before the deadline', function () {
    $booking = Booking::factory()->for($this->customer)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 10:00')]);

    Livewire::test('pages::my-bookings.my-booking-index')->call('cancel', $booking->id);

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});

test('keeps the booking after the deadline', function () {
    $booking = Booking::factory()->for($this->customer)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 08:30')]);

    Livewire::test('pages::my-bookings.my-booking-index')->call('cancel', $booking->id);

    expect($booking->fresh()->status)->toBe(BookingStatus::Scheduled);
});

test('cannot cancel bookings of another customer', function () {
    $booking = Booking::factory()->create(['starts_at' => CarbonImmutable::parse('2026-11-02 10:00')]);

    Livewire::test('pages::my-bookings.my-booking-index')
        ->call('cancel', $booking->id)
        ->assertNotFound();

    expect($booking->fresh()->status)->toBe(BookingStatus::Scheduled);
});

test('lists and cancels the customer bookings of every barbershop', function () {
    $other = Tenant::factory()->create(['name' => 'Navalha Vizinha']);
    $booking = inTenant($other, function () {
        $record = Customer::factory()->create(['user_id' => $this->customer->user_id]);

        return Booking::factory()->for($record)->for(Service::factory()->create(['name' => 'Barba vizinha']))
            ->create(['starts_at' => CarbonImmutable::parse('2026-11-04 10:00')]);
    });
    Booking::factory()->for($this->customer)->for(Service::factory()->create(['name' => 'Corte daqui']))
        ->create(['starts_at' => CarbonImmutable::parse('2026-11-03 10:00')]);

    $this->get(route('my-bookings.index'))
        ->assertOk()
        ->assertSee('Corte daqui')
        ->assertSee('Barba vizinha')
        ->assertSee('Navalha Vizinha')
        ->assertSee($this->tenant->name);

    Livewire::test('pages::my-bookings.my-booking-index')
        ->assertCount('upcoming', 2)
        ->call('cancel', $booking->id);

    expect(inTenant($other, fn () => $booking->fresh()->status))->toBe(BookingStatus::Cancelled);
});
