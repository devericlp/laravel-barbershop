<?php

use App\Actions\Customers\DetachUserFromCustomer;
use App\Enums\UserRole;
use App\Exceptions\RoleChangeException;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;

test('unlinks the account removing the customer role and keeping the record', function () {
    $user = User::factory()->professional()->withRole(UserRole::Customer)->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    Booking::factory()->for($customer)->create();

    app(DetachUserFromCustomer::class)->handle($customer);

    expect($customer->fresh()->user_id)->toBeNull()
        ->and($customer->bookings()->count())->toBe(1)
        ->and($user->fresh()->userRoles())->toBe([UserRole::Professional]);
});

test('an account whose only role is customer cannot be unlinked', function () {
    app(DetachUserFromCustomer::class)->handle(Customer::factory()->create());
})->throws(RoleChangeException::class);
