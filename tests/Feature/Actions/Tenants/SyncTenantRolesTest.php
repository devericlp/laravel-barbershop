<?php

use App\Actions\Tenants\SyncTenantRoles;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('enables a custom role in the tenant', function () {
    $receptionist = Role::factory()->create(['name' => 'Recepcionista']);
    $roleIds = [...$this->tenant->roles()->pluck('roles.id')->all(), $receptionist->id];

    app(SyncTenantRoles::class)->handle($this->tenant, $roleIds);

    expect($this->tenant->fresh()->roles->pluck('name'))->toContain('Recepcionista');
});

test('removes a role without users in the tenant', function () {
    User::factory()->manager()->create();

    app(SyncTenantRoles::class)->handle($this->tenant, [Role::where('name', 'manager')->value('id')]);

    expect($this->tenant->fresh()->roles->pluck('name')->all())->toBe(['manager']);
});

test('refuses to remove a role that tenant users have', function () {
    Customer::factory()->create();

    expect(fn () => app(SyncTenantRoles::class)->handle($this->tenant, [Role::where('name', 'manager')->value('id')]))
        ->toThrow(ValidationException::class);

    expect($this->tenant->fresh()->roles->pluck('name'))->toContain('customer');
});
