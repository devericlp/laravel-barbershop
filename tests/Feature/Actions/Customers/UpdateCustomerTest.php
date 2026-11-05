<?php

use App\Actions\Customers\UpdateCustomer;
use App\Enums\UserRole;
use App\Exceptions\RoleChangeException;
use App\Models\Customer;
use App\Models\User;

test('updates the name and phone of a customer without account', function () {
    $customer = Customer::factory()->withoutUser()->create();

    app(UpdateCustomer::class)->handle($customer, 'Ana Lima', '(21) 99876-5432');

    expect($customer->fresh())
        ->name->toBe('Ana Lima')
        ->phone->toBe('21998765432')
        ->user_id->toBeNull();
});

test('renaming a linked customer keeps the account name', function () {
    $customer = Customer::factory()->named('Ana')->create();

    app(UpdateCustomer::class)->handle($customer, 'Ana Lima', '21998765432', $customer->user);

    expect($customer->fresh()->name)->toBe('Ana Lima')
        ->and($customer->user->fresh()->name)->toBe('Ana');
});

test('linking an account keeps the record name', function () {
    $customer = Customer::factory()->withoutUser()->create(['name' => 'João']);
    $user = User::factory()->manager()->create(['name' => 'João Silva']);

    app(UpdateCustomer::class)->handle($customer, 'João', $customer->phone, $user);

    expect($customer->fresh())
        ->user_id->toBe($user->id)
        ->name->toBe('João')
        ->and($user->fresh()->userRoles())->toBe([UserRole::Manager, UserRole::Customer]);
});

test('replacing the account unlinks the previous one', function () {
    $previous = User::factory()->manager()->withRole(UserRole::Customer)->create();
    $customer = Customer::factory()->create(['user_id' => $previous->id]);
    $next = User::factory()->manager()->create();

    app(UpdateCustomer::class)->handle($customer, 'Qualquer', $customer->phone, $next);

    expect($customer->fresh()->user_id)->toBe($next->id)
        ->and($previous->fresh()->userRoles())->toBe([UserRole::Manager]);
});

test('removing the only role of an account is refused', function () {
    $customer = Customer::factory()->create();

    app(UpdateCustomer::class)->handle($customer, $customer->name, $customer->phone);
})->throws(RoleChangeException::class);
