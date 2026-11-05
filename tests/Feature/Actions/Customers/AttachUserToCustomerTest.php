<?php

use App\Actions\Customers\AttachUserToCustomer;
use App\Enums\UserRole;
use App\Exceptions\CustomerLinkException;
use App\Exceptions\RoleNotEnabledException;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    $this->tenant->roles()->sync(Role::whereIn('name', ['manager', 'professional', 'customer'])->pluck('id'));
});

test('links an account giving it the customer role and its name', function () {
    $customer = Customer::factory()->withoutUser()->create(['name' => 'João']);
    $user = User::factory()->professional()->create(['name' => 'João Silva']);

    app(AttachUserToCustomer::class)->handle($customer, $user);

    expect($customer->fresh())->user_id->toBe($user->id)->name->toBe('João Silva')
        ->and($user->fresh()->userRoles())->toBe([UserRole::Professional, UserRole::Customer]);
});

test('a customer linked to another account is refused', function () {
    $customer = Customer::factory()->create();

    app(AttachUserToCustomer::class)->handle($customer, User::factory()->manager()->create());
})->throws(CustomerLinkException::class);

test('an account already linked to another customer is refused', function () {
    $linked = Customer::factory()->create();

    app(AttachUserToCustomer::class)->handle(Customer::factory()->withoutUser()->create(), $linked->user);
})->throws(CustomerLinkException::class);

test('linking is refused when the barbershop does not enable the customer role', function () {
    $this->tenant->roles()->sync(Role::where('name', 'manager')->pluck('id'));

    app(AttachUserToCustomer::class)->handle(Customer::factory()->withoutUser()->create(), User::factory()->manager()->create());
})->throws(RoleNotEnabledException::class);
