<?php

use App\Actions\Bookings\CreateCustomerBooking;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 07:00'));
    $this->service = Service::factory()->create();
    $this->professional = bookableProfessional($this->service);
    $this->user = User::factory()->customer()->create(['name' => 'Conta Global']);
    $this->startsAt = CarbonImmutable::parse('2026-11-02 09:30');
});

test('creates a record in the barbershop with the given name and books on it', function () {
    $booking = app(CreateCustomerBooking::class)
        ->handle($this->user, 'João', '11977776666', $this->professional->id, $this->service->id, $this->startsAt);

    $record = Customer::sole();

    expect($booking->customer_id)->toBe($record->id)
        ->and($record->user_id)->toBe($this->user->id)
        ->and($record->tenant_id)->toBe($this->tenant->id)
        ->and($record->name)->toBe('João')
        ->and($record->phone)->toBe('11977776666');
});

test('books on the existing record of the account ignoring the given details', function () {
    $record = Customer::factory()->create(['user_id' => $this->user->id, 'name' => 'Nome da Ficha']);

    $booking = app(CreateCustomerBooking::class)
        ->handle($this->user, 'Outro', '11977776666', $this->professional->id, $this->service->id, $this->startsAt);

    expect($booking->customer_id)->toBe($record->id)
        ->and($record->fresh()->name)->toBe('Nome da Ficha')
        ->and(Customer::count())->toBe(1);
});

test('links the record without account that has the mobile', function () {
    $record = Customer::factory()->withoutUser()->create(['phone' => '11977776666', 'name' => 'Nome Antigo']);

    app(CreateCustomerBooking::class)
        ->handle($this->user, 'João', '11977776666', $this->professional->id, $this->service->id, $this->startsAt);

    expect($record->fresh())
        ->user_id->toBe($this->user->id)
        ->name->toBe('João');
});

test('refuses a mobile of a record linked to another account', function () {
    Customer::factory()->create(['phone' => '11977776666']);

    expect(fn () => app(CreateCustomerBooking::class)
        ->handle($this->user, 'João', '11977776666', $this->professional->id, $this->service->id, $this->startsAt))
        ->toThrow(ValidationException::class);

    expect(Booking::count())->toBe(0);
});
