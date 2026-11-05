<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Professional;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 07:00'));
    $this->professional = Professional::factory()->create();
    $this->actingAs($this->professional->user);
});

test('shows only the own bookings without management actions', function () {
    Booking::factory()->for($this->professional)->for(Customer::factory()->named('Meu Cliente')->create())
        ->create(['starts_at' => CarbonImmutable::parse('2026-11-02 10:00')]);
    Booking::factory()->for(Customer::factory()->named('Cliente de Colega')->create())
        ->create(['starts_at' => CarbonImmutable::parse('2026-11-02 11:00')]);

    $this->get(route('bookings.index', ['date' => '2026-11-02']))
        ->assertOk()
        ->assertSee('Meu Cliente')
        ->assertDontSee('Cliente de Colega')
        ->assertDontSee('Novo agendamento');

    Livewire::test('pages::bookings.booking-index')->assertCount('bookings', 1);
});

test('completes and marks no-show on own bookings', function () {
    $first = Booking::factory()->for($this->professional)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 10:00')]);
    $second = Booking::factory()->for($this->professional)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 11:00')]);

    Livewire::test('pages::bookings.booking-index')
        ->call('complete', $first->id)
        ->call('markAsNoShow', $second->id);

    expect($first->fresh()->status)->toBe(BookingStatus::Completed)
        ->and($second->fresh()->status)->toBe(BookingStatus::NoShow);
});

test('cannot cancel own bookings', function () {
    $booking = Booking::factory()->for($this->professional)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 10:00')]);

    Livewire::test('pages::bookings.booking-index')
        ->call('cancel', $booking->id)
        ->assertForbidden();

    expect($booking->fresh()->status)->toBe(BookingStatus::Scheduled);
});

test('cannot touch bookings of a colleague', function () {
    $booking = Booking::factory()->create(['starts_at' => CarbonImmutable::parse('2026-11-02 10:00')]);

    Livewire::test('pages::bookings.booking-index')
        ->call('complete', $booking->id)
        ->assertNotFound();

    expect($booking->fresh()->status)->toBe(BookingStatus::Scheduled);
});
