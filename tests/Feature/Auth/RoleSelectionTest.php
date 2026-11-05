<?php

use App\Enums\UserRole;
use App\Http\Middleware\EnsureRoleIsSelected;
use App\Models\Customer;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->manager()->withRole(UserRole::Customer)->create();
    Customer::factory()->create(['user_id' => $this->user->id]);
});

test('a user with more than one role is sent to the role selection after login', function () {
    Livewire::test('pages::auth.auth-login')
        ->set('email', $this->user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('select-role', absolute: false));
});

test('login forgets the role chosen in a previous session', function () {
    $this->withSession([EnsureRoleIsSelected::SESSION_KEY => UserRole::Customer->value]);

    Livewire::test('pages::auth.auth-login')
        ->set('email', $this->user->email)
        ->set('password', 'password')
        ->call('login');

    expect(session(EnsureRoleIsSelected::SESSION_KEY))->toBeNull();
});

test('pages redirect to the role selection until a role is chosen', function () {
    $this->actingAs($this->user);

    $this->get(route('dashboard'))->assertRedirect(route('select-role'));
    $this->get(route('profile.edit'))->assertRedirect(route('select-role'));
    $this->get(route('home'))->assertRedirect(route('select-role'));

    $this->get(route('select-role'))
        ->assertOk()
        ->assertSee(UserRole::Manager->label())
        ->assertSee(UserRole::Customer->label());
});

test('choosing a role goes to its home page', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::auth.role-select')
        ->call('select', UserRole::Customer->value)
        ->assertRedirect(route('bookings.create'));

    expect(session(EnsureRoleIsSelected::SESSION_KEY))->toBe(UserRole::Customer->value);
});

test('permissions follow only the active role', function () {
    $this->actingAs($this->user);

    $this->withSession([EnsureRoleIsSelected::SESSION_KEY => UserRole::Customer->value]);
    $this->get(route('dashboard'))->assertForbidden();
    $this->get(route('users.index'))->assertForbidden();
    $this->get(route('my-bookings.index'))->assertOk();

    $this->withSession([EnsureRoleIsSelected::SESSION_KEY => UserRole::Manager->value]);
    $this->get(route('dashboard'))->assertOk();
    $this->get(route('my-bookings.index'))->assertForbidden();
});

test('a role the user does not have cannot be chosen', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::auth.role-select')
        ->call('select', UserRole::Professional->value)
        ->assertForbidden();

    $this->withSession([EnsureRoleIsSelected::SESSION_KEY => UserRole::Professional->value]);
    $this->get(route('dashboard'))->assertRedirect(route('select-role'));
});

test('a user with a single role skips the selection', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager);

    $this->get(route('dashboard'))->assertOk()->assertDontSeeLivewire('role-switcher');

    Livewire::test('pages::auth.role-select')->assertRedirect(route('dashboard'));
});

test('the sidebar switcher changes the active role', function () {
    $this->actingAs($this->user);
    $this->withSession([EnsureRoleIsSelected::SESSION_KEY => UserRole::Manager->value]);

    $this->get(route('dashboard'))->assertOk()->assertSeeLivewire('role-switcher');

    Livewire::test('role-switcher')
        ->assertSet('role', UserRole::Manager->value)
        ->set('role', UserRole::Customer->value)
        ->assertRedirect(route('bookings.create'));

    expect(session(EnsureRoleIsSelected::SESSION_KEY))->toBe(UserRole::Customer->value);
});
