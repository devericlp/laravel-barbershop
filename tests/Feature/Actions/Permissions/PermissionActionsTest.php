<?php

use App\Actions\Permissions\CreatePermission;
use App\Actions\Permissions\DeletePermission;
use App\Actions\Permissions\UpdatePermission;
use App\Exceptions\CannotDeleteRecordException;
use App\Models\Permission;
use Illuminate\Validation\ValidationException;

test('creates a custom permission', function () {
    $permission = app(CreatePermission::class)->handle('reports.index');

    expect($permission->is_system)->toBeFalse()
        ->and($permission->name)->toBe('reports.index');
});

test('renames and deletes a custom permission', function () {
    $permission = Permission::factory()->create(['name' => 'reports.index']);

    app(UpdatePermission::class)->handle($permission, 'reports.export');
    expect($permission->fresh()->name)->toBe('reports.export');

    app(DeletePermission::class)->handle($permission);
    expect(Permission::find($permission->id))->toBeNull();
});

test('refuses to rename a system permission', function () {
    expect(fn () => app(UpdatePermission::class)->handle(Permission::where('name', 'users.index')->sole(), 'people.index'))
        ->toThrow(ValidationException::class);
});

test('refuses to delete a system permission', function () {
    expect(fn () => app(DeletePermission::class)->handle(Permission::where('name', 'users.index')->sole()))
        ->toThrow(CannotDeleteRecordException::class);
});
