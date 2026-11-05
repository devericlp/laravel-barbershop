<?php

use App\Actions\Tenants\CreateTenant;
use App\Enums\UserRole;
use App\Exceptions\RoleNotEnabledException;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => app(CurrentTenant::class)->set(null));

test('creates an active tenant with its roles and first manager', function () {
    $roleIds = Role::whereIn('name', ['manager', 'customer'])->pluck('id')->all();

    $tenant = app(CreateTenant::class)->handle('Barbearia Nova', 'Paula Gerente', 'paula@email.com', 'secret-password', $roleIds);

    $manager = User::where('email', 'paula@email.com')->sole();

    expect($tenant->is_active)->toBeTrue()
        ->and($tenant->name)->toBe('Barbearia Nova')
        ->and($tenant->roles()->pluck('name')->sort()->values()->all())->toBe(['customer', 'manager'])
        ->and($manager->tenant_id)->toBe($tenant->id)
        ->and($manager->userRoles())->toBe([UserRole::Manager])
        ->and($manager->hasRole('manager'))->toBeTrue()
        ->and(Hash::check('secret-password', $manager->password))->toBeTrue();
});

test('never enables the admin role in a tenant', function () {
    $roleIds = Role::whereIn('name', ['admin', 'manager'])->pluck('id')->all();

    $tenant = app(CreateTenant::class)->handle('Barbearia Nova', 'Paula', 'paula@email.com', 'secret-password', $roleIds);

    expect($tenant->roles()->pluck('name')->all())->toBe(['manager']);
});

test('requires the manager role', function () {
    $roleIds = Role::where('name', 'customer')->pluck('id')->all();

    expect(fn () => app(CreateTenant::class)->handle('Barbearia Nova', 'Paula', 'paula@email.com', 'secret-password', $roleIds))
        ->toThrow(RoleNotEnabledException::class);

    expect(User::where('email', 'paula@email.com')->exists())->toBeFalse();
});
