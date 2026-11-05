<?php

use App\Actions\Users\DeleteUser;
use App\Exceptions\CannotDeleteRecordException;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\User;

beforeEach(function () {
    $this->actor = User::factory()->manager()->create();
});

test('deletes another manager', function () {
    $manager = User::factory()->manager()->create();

    app(DeleteUser::class)->handle($manager, $this->actor);

    expect(User::find($manager->id))->toBeNull();
});

test('nobody deletes their own account', function () {
    User::factory()->manager()->create();

    app(DeleteUser::class)->handle($this->actor, $this->actor);
})->throws(CannotDeleteRecordException::class);

test('the tenant keeps at least one manager', function () {
    $admin = User::factory()->admin()->create();

    app(DeleteUser::class)->handle($this->actor, $admin);
})->throws(CannotDeleteRecordException::class);

test('deleting a customer account keeps the record and its bookings', function () {
    $customer = Customer::factory()->create();
    Booking::factory()->for($customer)->create();

    app(DeleteUser::class)->handle($customer->user, $this->actor);

    expect($customer->fresh()->user_id)->toBeNull()
        ->and($customer->bookings()->count())->toBe(1);
});

test('deletes a customer account keeping the unlinked customer record', function () {
    $customer = Customer::factory()->create();

    app(DeleteUser::class)->handle($customer->user, $this->actor);

    expect($customer->fresh()->user_id)->toBeNull()
        ->and(User::find($customer->user_id))->toBeNull();
});

test('deletes a professional without bookings with its record', function () {
    $professional = Professional::factory()->create();

    app(DeleteUser::class)->handle($professional->user, $this->actor);

    expect(Professional::find($professional->id))->toBeNull()
        ->and(User::find($professional->user_id))->toBeNull();
});
