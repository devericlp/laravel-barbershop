<?php

use App\Actions\Roles\CreateRole;
use App\Actions\Roles\DeleteRole;
use App\Actions\Roles\UpdateRole;
use App\Exceptions\CannotDeleteRecordException;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('creates a custom role with permissions', function () {
    $permissionIds = Permission::whereIn('name', ['bookings.index', 'bookings.create'])->pluck('id')->all();

    $role = app(CreateRole::class)->handle('Recepcionista', $permissionIds);

    expect($role->is_system)->toBeFalse()
        ->and($role->permissions->pluck('name')->sort()->values()->all())->toBe(['bookings.create', 'bookings.index']);
});

test('changes the permissions of a system role', function () {
    $user = User::factory()->manager()->create();

    app(UpdateRole::class)->handle(Role::where('name', 'manager')->sole(), 'manager', Permission::where('name', 'dashboard.index')->pluck('id')->all());

    expect($user->fresh()->can('dashboard.index'))->toBeTrue()
        ->and($user->fresh()->can('services.create'))->toBeFalse();
});

test('refuses to rename a system role', function () {
    expect(fn () => app(UpdateRole::class)->handle(Role::where('name', 'manager')->sole(), 'Chefe', []))
        ->toThrow(ValidationException::class);
});

test('refuses to change the admin role', function () {
    expect(fn () => app(UpdateRole::class)->handle(Role::where('name', 'admin')->sole(), 'admin', []))
        ->toThrow(ValidationException::class);
});

test('renames a custom role', function () {
    $role = Role::factory()->create(['name' => 'Recepção']);

    app(UpdateRole::class)->handle($role, 'Recepcionista', []);

    expect($role->fresh()->name)->toBe('Recepcionista');
});

test('deletes a custom role without users', function () {
    $role = Role::factory()->create();

    app(DeleteRole::class)->handle($role);

    expect(Role::find($role->id))->toBeNull();
});

test('refuses to delete a system role', function () {
    expect(fn () => app(DeleteRole::class)->handle(Role::where('name', 'customer')->sole()))
        ->toThrow(CannotDeleteRecordException::class);
});

test('refuses to delete a custom role with users', function () {
    $role = Role::factory()->create();
    User::factory()->manager()->create()->assignRole($role);

    expect(fn () => app(DeleteRole::class)->handle($role))
        ->toThrow(CannotDeleteRecordException::class);
});
