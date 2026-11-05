<?php

use App\Models\Customer;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    User::factory()->manager()->create();
    app(CurrentTenant::class)->set(null);
    $this->actingAs(User::factory()->admin()->create());
});

test('lists tenants with their users', function () {
    $this->get(route('tenants.index'))
        ->assertOk()
        ->assertSee($this->tenant->name);

    expect(Livewire::test('pages::tenants.tenant-index')->get('tenants')->firstWhere('id', $this->tenant->id)->users_count)->toBe(1);
});

test('searches tenants by name and shows the search as a filter', function () {
    Tenant::factory()->create(['name' => 'Barbearia Centro']);

    $component = Livewire::test('pages::tenants.tenant-index')->set('searchInput', 'Centro')->call('applySearch');

    expect($component->get('tenants')->pluck('name')->all())->toBe(['Barbearia Centro'])
        ->and($component->get('activeFilters'))->toBe([['key' => 'search', 'label' => 'Pesquisa: Centro']]);
});

test('searches only when the search is submitted', function () {
    Tenant::factory()->create(['name' => 'Barbearia Centro']);

    $component = Livewire::test('pages::tenants.tenant-index')->set('searchInput', '  Centro ');

    expect($component->get('tenants')->total())->toBe(2)
        ->and($component->get('activeFilters'))->toBe([]);

    $component->call('applySearch')->assertSet('search', 'Centro')->assertSet('searchInput', 'Centro');

    expect($component->get('tenants')->pluck('name')->all())->toBe(['Barbearia Centro']);

    $component->call('removeFilter', 'search')->assertSet('search', '')->assertSet('searchInput', '');
});

test('applies filters only when confirmed', function () {
    Tenant::factory()->create(['name' => 'Barbearia Fechada', 'is_active' => false]);

    $component = Livewire::test('pages::tenants.tenant-index')->set('filters.status', 'inactive');

    expect($component->get('tenants')->total())->toBe(2)
        ->and($component->get('activeFilters'))->toBe([]);

    $component->call('applyFilters')->assertHasNoErrors();

    expect($component->get('tenants')->pluck('name')->all())->toBe(['Barbearia Fechada'])
        ->and($component->get('activeFilters'))->toBe([['key' => 'status', 'label' => 'Status: Inativo']]);
});

test('filters tenants by enabled role and bookings', function () {
    $receptionist = Role::factory()->create(['name' => 'Recepcionista']);
    $tenant = Tenant::factory()->create(['name' => 'Barbearia Recepção']);
    $tenant->roles()->attach($receptionist);

    Livewire::test('pages::tenants.tenant-index')
        ->set('filters.role', (string) $receptionist->id)
        ->set('filters.bookings', 'without')
        ->call('applyFilters')
        ->assertSee('Barbearia Recepção')
        ->assertDontSee($this->tenant->name)
        ->assertSee('Perfil habilitado: Recepcionista')
        ->assertSee('Agendamentos: Sem agendamentos');
});

test('rejects unknown filter values', function () {
    Livewire::test('pages::tenants.tenant-index')
        ->set('filters.status', 'archived')
        ->call('applyFilters')
        ->assertHasErrors(['filters.status'])
        ->assertSet('appliedFilters', []);
});

test('removes one filter and keeps the others', function () {
    Livewire::test('pages::tenants.tenant-index')
        ->set('searchInput', 'Barbearia')->call('applySearch')
        ->set('filters.status', 'active')
        ->set('filters.bookings', 'without')
        ->call('applyFilters')
        ->call('removeFilter', 'status')
        ->assertSet('filters', ['bookings' => 'without'])
        ->assertSet('appliedFilters', ['bookings' => 'without'])
        ->assertSet('search', 'Barbearia')
        ->call('removeFilter', 'search')
        ->assertSet('search', '');
});

test('clears every filter including the search', function () {
    Livewire::test('pages::tenants.tenant-index')
        ->set('searchInput', 'Nada')->call('applySearch')
        ->set('filters.status', 'inactive')
        ->call('applyFilters')
        ->assertSee('Nenhuma barbearia encontrada')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('filters', [])
        ->assertSet('appliedFilters', [])
        ->assertSee($this->tenant->name);
});

test('paginates in the database and returns to the first page when the criteria change', function () {
    Tenant::factory()->count(12)->create();

    $component = Livewire::test('pages::tenants.tenant-index')->call('gotoPage', 2);

    expect($component->get('tenants')->count())->toBe(3)
        ->and($component->get('tenants')->total())->toBe(13);

    $component->set('searchInput', 'a')->call('applySearch');
    expect($component->get('tenants')->currentPage())->toBe(1);

    $component->call('gotoPage', 2)->call('applyFilters');
    expect($component->get('tenants')->currentPage())->toBe(1);

    $component->call('gotoPage', 2)->call('removeFilter', 'search');
    expect($component->get('tenants')->currentPage())->toBe(1);
});

test('renders the page without one query per row', function () {
    $queriesFor = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test('pages::tenants.tenant-index');
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $fewRows = $queriesFor();
    Tenant::factory()->count(8)->create();

    expect($queriesFor())->toBe($fewRows);
});

test('only accepts the offered page sizes', function () {
    Livewire::test('pages::tenants.tenant-index')
        ->set('perPage', 25)
        ->assertSet('perPage', 25)
        ->set('perPage', 1000)
        ->assertSet('perPage', 10);
});

test('creates a tenant whose manager can sign in', function () {
    $component = Livewire::test('pages::tenants.tenant-index')->call('create');

    expect($component->get('roleIds'))->toHaveCount(3);

    $component
        ->set('name', 'Barbearia Nova')
        ->set('managerName', 'Paula')
        ->set('managerEmail', 'paula@email.com')
        ->set('managerPassword', 'password')
        ->call('save')
        ->assertHasNoErrors();

    $tenant = Tenant::where('name', 'Barbearia Nova')->sole();

    expect($tenant->roles->pluck('name')->sort()->values()->all())->toBe(['customer', 'manager', 'professional']);

    $this->post(route('logout'));

    Livewire::test('pages::auth.auth-login')
        ->set('email', 'paula@email.com')
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect(auth()->user()->tenant_id)->toBe($tenant->id);
});

test('requires the manager role when creating a tenant', function () {
    Livewire::test('pages::tenants.tenant-index')
        ->call('create')
        ->set('name', 'Barbearia Nova')
        ->set('managerName', 'Paula')
        ->set('managerEmail', 'paula@email.com')
        ->set('managerPassword', 'password')
        ->set('roleIds', [(string) Role::where('name', 'customer')->value('id')])
        ->call('save')
        ->assertHasErrors(['roleIds']);
});

test('rejects an email already in use', function () {
    $taken = User::withoutGlobalScopes()->where('role', 'manager')->value('email');

    Livewire::test('pages::tenants.tenant-index')
        ->call('create')
        ->set('name', 'Barbearia Nova')
        ->set('managerName', 'Paula')
        ->set('managerEmail', $taken)
        ->set('managerPassword', 'password')
        ->call('save')
        ->assertHasErrors(['managerEmail']);
});

test('deactivates a tenant', function () {
    Livewire::test('pages::tenants.tenant-index')
        ->call('edit', $this->tenant->id)
        ->assertSet('name', $this->tenant->name)
        ->set('isActive', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->tenant->fresh()->is_active)->toBeFalse();
});

test('enables a custom role in a tenant', function () {
    $receptionist = Role::factory()->create(['name' => 'Recepcionista']);

    $component = Livewire::test('pages::tenants.tenant-index')->call('edit', $this->tenant->id);

    $component
        ->set('roleIds', [...$component->get('roleIds'), (string) $receptionist->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($this->tenant->fresh()->roles->pluck('name'))->toContain('Recepcionista');
});

test('refuses to remove a role that tenant users have', function () {
    inTenant($this->tenant, fn () => Customer::factory()->create());

    Livewire::test('pages::tenants.tenant-index')
        ->call('edit', $this->tenant->id)
        ->set('roleIds', [(string) Role::where('name', 'manager')->value('id')])
        ->call('save')
        ->assertHasErrors(['roleIds']);

    expect($this->tenant->fresh()->roles->pluck('name'))->toContain('customer');
});

test('does not offer the admin role', function () {
    $options = Livewire::test('pages::tenants.tenant-index')->get('roleOptions');

    expect($options->pluck('name'))->not->toContain('admin');
});
