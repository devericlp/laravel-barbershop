<?php

use App\Actions\Users\CreateUser;
use App\Enums\UserRole;
use App\Exceptions\RoleNotEnabledException;
use App\Models\Role;

test('creates a user with the role in the current tenant', function () {
    $user = app(CreateUser::class)->handle(UserRole::Manager, 'Carla', 'carla@email.com', 'secret-password');

    expect($user->userRoles())->toBe([UserRole::Manager])
        ->and($user->tenant_id)->toBe($this->tenant->id)
        ->and($user->hasRole('manager'))->toBeTrue()
        ->and($user->can('services.index'))->toBeTrue();
});

test('refuses a role that is not enabled in the tenant', function () {
    $this->tenant->roles()->detach(Role::where('name', 'customer')->value('id'));

    expect(fn () => app(CreateUser::class)->handle(UserRole::Customer, 'Carla', 'carla@email.com', 'secret-password'))
        ->toThrow(RoleNotEnabledException::class);
});
