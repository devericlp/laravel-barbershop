<?php

use App\Actions\Customers\LinkUserAsCustomer;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('links the customer without account that has the mobile, taking the account name', function () {
    $customer = Customer::factory()->withoutUser()->create(['name' => 'João', 'phone' => '11912345678']);
    $user = User::factory()->professional()->create(['name' => 'João Silva']);

    app(LinkUserAsCustomer::class)->handle($user, '(11) 91234-5678');

    expect($customer->fresh())->user_id->toBe($user->id)->name->toBe('João Silva')
        ->and($user->fresh()->userRoles())->toBe([UserRole::Professional, UserRole::Customer]);
});

test('creates a customer when no one has the mobile', function () {
    $user = User::factory()->manager()->create(['name' => 'Ana']);

    app(LinkUserAsCustomer::class)->handle($user, '11912345678');

    expect($user->fresh()->customer)->name->toBe('Ana')->phone->toBe('11912345678');
});

test('the mobile of a customer with another account is refused', function () {
    Customer::factory()->create(['phone' => '11912345678']);

    app(LinkUserAsCustomer::class)->handle(User::factory()->manager()->create(), '11912345678');
})->throws(ValidationException::class);

test('changing the mobile to one of another customer is refused', function () {
    Customer::factory()->withoutUser()->create(['phone' => '11977776666']);
    $customer = Customer::factory()->create(['phone' => '11912345678']);

    app(LinkUserAsCustomer::class)->handle($customer->user, '11977776666');
})->throws(ValidationException::class);
