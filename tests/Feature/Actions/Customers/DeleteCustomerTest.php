<?php

use App\Actions\Customers\DeleteCustomer;
use App\Exceptions\CannotDeleteRecordException;
use App\Models\Booking;
use App\Models\Customer;

test('deletes a customer without account and bookings', function () {
    $customer = Customer::factory()->withoutUser()->create();

    app(DeleteCustomer::class)->handle($customer);

    expect(Customer::find($customer->id))->toBeNull();
});

test('a customer with bookings cannot be deleted', function () {
    $customer = Customer::factory()->withoutUser()->create();
    Booking::factory()->for($customer)->create();

    app(DeleteCustomer::class)->handle($customer);
})->throws(CannotDeleteRecordException::class);

test('a customer linked to an account cannot be deleted', function () {
    app(DeleteCustomer::class)->handle(Customer::factory()->create());
})->throws(CannotDeleteRecordException::class);
