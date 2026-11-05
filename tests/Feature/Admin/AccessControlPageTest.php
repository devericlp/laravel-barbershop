<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    app(CurrentTenant::class)->set(null);
    $this->actingAs(User::factory()->admin()->create());
});

test('lists roles without the admin role and the permissions', function () {
    $this->get(route('access-control.index'))
        ->assertOk()
        ->assertSee(__('messages.manager'))
        ->assertSee('users.index');

    expect(Livewire::test('access-control.roles-table')->get('roles')->pluck('name'))->not->toContain('admin');
});

test('only the admin opens the page', function () {
    $this->actingAs(inTenant($this->tenant, fn () => User::factory()->manager()->create()))
        ->get(route('access-control.index'))
        ->assertForbidden();
});

test('creates a custom role with permissions', function () {
    $permissionId = (string) Permission::where('name', 'bookings.index')->value('id');

    Livewire::test('access-control.roles-table')
        ->call('createRole')
        ->set('roleName', 'Recepcionista')
        ->set('rolePermissionIds', [$permissionId])
        ->call('saveRole')
        ->assertHasNoErrors();

    expect(Role::where('name', 'Recepcionista')->sole()->permissions->pluck('name')->all())->toBe(['bookings.index']);
});

test('changes the permissions of a system role', function () {
    $manager = Role::where('name', 'manager')->sole();

    Livewire::test('access-control.roles-table')
        ->call('editRole', $manager->id)
        ->assertSet('roleName', 'manager')
        ->set('rolePermissionIds', [(string) Permission::where('name', 'dashboard.index')->value('id')])
        ->call('saveRole')
        ->assertHasNoErrors();

    expect($manager->fresh()->permissions->pluck('name')->all())->toBe(['dashboard.index']);
});

test('refuses to rename a system role', function () {
    Livewire::test('access-control.roles-table')
        ->call('editRole', Role::where('name', 'manager')->value('id'))
        ->set('roleName', 'Chefe')
        ->call('saveRole')
        ->assertHasErrors(['name']);

    expect(Role::where('name', 'manager')->exists())->toBeTrue();
});

test('cannot open the admin role', function () {
    Livewire::test('access-control.roles-table')
        ->call('editRole', Role::where('name', 'admin')->value('id'))
        ->assertNotFound();
});

test('deletes a custom role', function () {
    $role = Role::factory()->create();

    Livewire::test('access-control.roles-table')
        ->call('confirmDeleteRole', $role->id)
        ->call('deleteRole');

    expect(Role::find($role->id))->toBeNull();
});

test('keeps a system role when asked to delete it', function () {
    $customer = Role::where('name', 'customer')->sole();

    Livewire::test('access-control.roles-table')
        ->call('confirmDeleteRole', $customer->id)
        ->call('deleteRole');

    expect(Role::find($customer->id))->not->toBeNull();
});

test('creates, renames and deletes a custom permission', function () {
    $component = Livewire::test('access-control.permissions-table')
        ->call('createPermission')
        ->set('permissionName', 'reports.index')
        ->call('savePermission')
        ->assertHasNoErrors();

    $permission = Permission::where('name', 'reports.index')->sole();

    $component->call('editPermission', $permission->id)
        ->set('permissionName', 'reports.export')
        ->call('savePermission')
        ->assertHasNoErrors();

    expect($permission->fresh()->name)->toBe('reports.export');

    $component->call('confirmDeletePermission', $permission->id)->call('deletePermission');

    expect(Permission::find($permission->id))->toBeNull();
});

test('validates the permission name format', function () {
    Livewire::test('access-control.permissions-table')
        ->call('createPermission')
        ->set('permissionName', 'Relatórios')
        ->call('savePermission')
        ->assertHasErrors(['permissionName' => 'regex']);
});

test('searches roles by the translated label and filters by type', function () {
    Role::factory()->create(['name' => 'Recepcionista']);

    $component = Livewire::test('access-control.roles-table')
        ->set('searchInput', 'gerente')
        ->call('applySearch');

    expect($component->get('roles')->pluck('name')->all())->toBe(['manager']);

    $component->call('clearFilters')
        ->set('filters.type', 'custom')
        ->call('applyFilters')
        ->assertSee('Tipo: Personalizado');

    expect($component->get('roles')->pluck('name')->all())->toBe(['Recepcionista']);
});

test('searches permissions and filters by type', function () {
    Permission::factory()->create(['name' => 'reports.index', 'is_system' => false]);

    $component = Livewire::test('access-control.permissions-table')
        ->set('filters.type', 'custom')
        ->call('applyFilters');

    expect($component->get('permissions')->pluck('name')->all())->toBe(['reports.index']);

    $component->call('clearFilters')->set('searchInput', 'services.')->call('applySearch');

    expect($component->get('permissions')->pluck('name')->every(fn (string $name) => str_starts_with($name, 'services.')))->toBeTrue();
});

test('mounts only the active tab', function () {
    Livewire::test('pages::access-control.access-control-index')
        ->assertSeeLivewire('access-control.roles-table')
        ->assertDontSeeLivewire('access-control.permissions-table')
        ->set('tab', 'permissions')
        ->assertSeeLivewire('access-control.permissions-table')
        ->assertSee('Nova permissão');
});

test('lists roles and permissions without one query per row', function () {
    foreach (['access-control.roles-table', 'access-control.permissions-table'] as $component) {
        $queriesFor = function () use ($component): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test($component);
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $queriesFor();
        $fewRows = $queriesFor();
        Role::factory()->count(3)->create();
        Permission::factory()->count(3)->create();

        expect($queriesFor())->toBe($fewRows);
    }
});
