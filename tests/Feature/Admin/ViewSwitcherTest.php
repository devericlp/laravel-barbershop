<?php

use App\Http\Middleware\SetCurrentTenant;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Livewire\Livewire;

beforeEach(function () {
    $this->otherTenant = Tenant::factory()->create(['name' => 'Navalha de Ouro']);
    inTenant($this->tenant, fn () => Service::factory()->create(['name' => 'Corte daqui']));
    inTenant($this->otherTenant, fn () => Service::factory()->create(['name' => 'Corte alheio']));

    app(CurrentTenant::class)->set(null);
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('the platform view shows only the admin menu and pages', function () {
    $this->get(route('tenants.index'))
        ->assertOk()
        ->assertSee(route('access-control.index'))
        ->assertDontSee(route('services.index'));

    $this->get(route('services.index'))->assertForbidden();
});

test('choosing a tenant switches the view to its dashboard', function () {
    Livewire::test('view-switcher')
        ->assertSet('view', 'admin')
        ->set('view', (string) $this->tenant->id)
        ->assertRedirect(route('dashboard'));

    expect(session(SetCurrentTenant::SESSION_KEY))->toBe($this->tenant->id);
});

test('the tenant view shows the tenant data and menu and hides the admin pages', function () {
    $this->withSession([SetCurrentTenant::SESSION_KEY => $this->tenant->id]);

    $this->get(route('services.index'))
        ->assertOk()
        ->assertSee('Corte daqui')
        ->assertDontSee('Corte alheio')
        ->assertSee(route('users.index'))
        ->assertDontSee(route('access-control.index'));

    $this->get(route('tenants.index'))->assertForbidden();
    $this->get(route('access-control.index'))->assertForbidden();
    $this->get(route('home'))->assertRedirect(route('dashboard'));
});

test('the admin keeps the bypass inside a tenant', function () {
    $this->withSession([SetCurrentTenant::SESSION_KEY => $this->tenant->id])
        ->get(route('services.index'))
        ->assertOk();

    Livewire::test('pages::services.service-index')
        ->call('create')
        ->set('name', 'Barba')
        ->set('price', '35,00')
        ->set('durationMinutes', 30)
        ->call('save')
        ->assertHasNoErrors();

    expect(Service::withoutGlobalScopes()->where('name', 'Barba')->value('tenant_id'))->toBe($this->tenant->id);
});

test('choosing admin returns to the platform view', function () {
    $this->withSession([SetCurrentTenant::SESSION_KEY => $this->tenant->id]);
    app(CurrentTenant::class)->set($this->tenant);

    Livewire::test('view-switcher')
        ->assertSet('view', (string) $this->tenant->id)
        ->set('view', 'admin')
        ->assertRedirect(route('tenants.index'));

    expect(session()->has(SetCurrentTenant::SESSION_KEY))->toBeFalse();
});

test('only the admin can use the switcher', function () {
    $manager = inTenant($this->tenant, fn () => User::factory()->manager()->create());

    $this->actingAs($manager);

    Livewire::test('view-switcher')->assertForbidden();
});
