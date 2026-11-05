<?php

use App\Models\Customer;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;

test('a revoked permission hides the button and blocks the action', function () {
    Role::findByName('manager')->revokePermissionTo('services.create');
    $this->actingAs(User::factory()->manager()->create());

    $this->get(route('services.index'))
        ->assertOk()
        ->assertDontSee('wire:click="create"', false);

    Livewire::test('pages::services.service-index')
        ->call('create')
        ->assertForbidden();
});

test('a revoked page permission blocks the route and hides the menu item', function () {
    Role::findByName('manager')->revokePermissionTo('users.index');
    $this->actingAs(User::factory()->manager()->create());

    $this->get(route('users.index'))->assertForbidden();
    $this->get(route('services.index'))->assertOk()->assertDontSee(route('users.index'));
});

test('a revoked destroy permission blocks deleting a service', function () {
    Role::findByName('manager')->revokePermissionTo('services.destroy');
    $this->actingAs(User::factory()->manager()->create());
    $service = Service::factory()->create();

    Livewire::test('pages::services.service-index')
        ->call('confirmDelete', $service->id)
        ->assertForbidden();

    expect($service->fresh())->not->toBeNull();
});

test('a role disabled in the tenant hides its option in the new user menu', function () {
    $this->tenant->roles()->detach(Role::findByName('professional')->id);
    $this->actingAs(User::factory()->manager()->create());

    expect(collect(Livewire::test('pages::users.user-index')->get('creatableRoles'))->pluck('role')->all())
        ->toBe(['manager']);
});

test('the home page falls back to the first permitted page', function () {
    Role::findByName('manager')->revokePermissionTo('dashboard.index');
    $this->actingAs(User::factory()->manager()->create());

    $this->get(route('home'))->assertRedirect(route('bookings.index'));
});

test('the home page falls back to the profile without any page permission', function () {
    Role::findByName('customer')->syncPermissions([]);
    $this->actingAs(Customer::factory()->create()->user);

    $this->get(route('home'))->assertRedirect(route('profile.edit'));
});

test('the admin cannot open barbershop pages', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('dashboard'))->assertForbidden();
    $this->get(route('services.index'))->assertForbidden();
});
