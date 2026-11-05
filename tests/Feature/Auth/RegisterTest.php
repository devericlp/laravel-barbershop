<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function registerPage(?string $slug): Testable
{
    return Livewire::withQueryParams(array_filter(['barbershop' => $slug]))->test('pages::auth.auth-register');
}

beforeEach(function () {
    app(CurrentTenant::class)->set(null);
});

test('the register page opens with the barbershop chosen in the booking', function () {
    $this->get(route('register', ['barbershop' => $this->tenant->slug]))
        ->assertOk()
        ->assertSee('Criar conta')
        ->assertSee($this->tenant->name);
});

test('without a barbershop that accepts customers the register page goes to the public booking', function () {
    $inactive = Tenant::factory()->inactive()->create();
    $withoutCustomers = Tenant::factory()->create();
    $withoutCustomers->roles()->detach(Role::where('name', UserRole::Customer->value)->value('id'));

    $this->get(route('register'))->assertRedirect(route('public-bookings.create'));
    $this->get(route('register', ['barbershop' => $inactive->slug]))->assertRedirect(route('public-bookings.create'));
    $this->get(route('register', ['barbershop' => $withoutCustomers->slug]))->assertRedirect(route('public-bookings.create'));
    $this->get(route('register', ['barbershop' => 'nao-existe']))->assertRedirect(route('public-bookings.create'));
});

test('a logged user cannot open the register page', function () {
    $this->actingAs(User::factory()->manager()->create())
        ->get(route('register', ['barbershop' => $this->tenant->slug]))
        ->assertRedirect();
});

test('registers a customer account in the barbershop, logs in and goes to the booking', function () {
    registerPage($this->tenant->slug)
        ->set('name', 'Ana Souza')
        ->set('email', 'ana@example.com')
        ->set('phone', '(11) 98888-7777')
        ->set('password', 'password123')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('bookings.create', ['barbershop' => $this->tenant->slug]));

    $user = User::withoutGlobalScopes()->where('email', 'ana@example.com')->sole();

    app(CurrentTenant::class)->set($this->tenant);

    expect($user->tenant_id)->toBeNull()
        ->and($user->hasUserRole(UserRole::Customer))->toBeTrue()
        ->and($user->customer->phone)->toBe('11988887777')
        ->and($user->customer->tenant_id)->toBe($this->tenant->id);

    $this->assertAuthenticatedAs($user);
});

test('links the account to the customer record that already has the mobile', function () {
    $existing = inTenant($this->tenant, fn () => Customer::factory()->withoutUser()->create(['phone' => '11988887777']));

    registerPage($this->tenant->slug)
        ->set('name', 'Ana Souza')
        ->set('email', 'ana@example.com')
        ->set('phone', '11988887777')
        ->set('password', 'password123')
        ->call('register')
        ->assertHasNoErrors();

    expect($existing->fresh()->user_id)->toBe(User::withoutGlobalScopes()->where('email', 'ana@example.com')->value('id'))
        ->and(inTenant($this->tenant, fn () => Customer::count()))->toBe(1);
});

test('validates the fields and refuses a mobile that already belongs to another account', function () {
    $taken = inTenant($this->tenant, fn () => Customer::factory()->create(['phone' => '11988887777']));

    registerPage($this->tenant->slug)
        ->call('register')
        ->assertHasErrors(['name' => 'required', 'email' => 'required', 'phone' => 'required', 'password' => 'required'])
        ->set('name', 'Ana Souza')
        ->set('email', $taken->user->email)
        ->set('phone', '1234')
        ->set('password', 'password123')
        ->call('register')
        ->assertHasErrors(['email' => 'unique', 'phone'])
        ->set('email', 'ana@example.com')
        ->set('phone', '11988887777')
        ->call('register')
        ->assertHasErrors(['phone']);

    expect(User::withoutGlobalScopes()->where('email', 'ana@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});

test('the login page links to the public booking to create an account', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Ainda não tem conta?')
        ->assertSee(route('public-bookings.create'), false);
});

test('the booking page links to the register page only for visitors who chose a barbershop', function () {
    $service = inTenant($this->tenant, fn () => Service::factory()->create());
    inTenant($this->tenant, fn () => bookableProfessional($service));

    Livewire::test('pages::bookings.booking-create')
        ->assertDontSee('Ainda não tem conta?')
        ->call('selectBarbershop', $this->tenant->id)
        ->assertSee('Ainda não tem conta?')
        ->assertSee(route('register', ['barbershop' => $this->tenant->slug]), false);

    $this->actingAs(User::factory()->manager()->create());

    Livewire::test('pages::bookings.booking-create')
        ->call('selectBarbershop', $this->tenant->id)
        ->assertDontSee('Ainda não tem conta?');
});
