<?php

use App\Actions\Customers\CreateCustomer;
use App\Enums\UserRole;
use App\Models\User;

test('creates a customer without an account with a normalized phone', function () {
    $customer = app(CreateCustomer::class)->handle('João Silva', '+55 (11) 91234-5678');

    expect($customer->phone)->toBe('11912345678')
        ->and($customer->tenant_id)->toBe($this->tenant->id)
        ->and($customer->name)->toBe('João Silva')
        ->and($customer->user_id)->toBeNull();
});

test('creates a customer linked to an account keeping the given name', function () {
    $user = User::factory()->professional()->create(['name' => 'Rafael Mendes']);

    $customer = app(CreateCustomer::class)->handle('Rafa', '11912345678', $user);

    expect($customer->user_id)->toBe($user->id)
        ->and($customer->name)->toBe('Rafa')
        ->and($user->fresh()->userRoles())->toBe([UserRole::Professional, UserRole::Customer]);
});
