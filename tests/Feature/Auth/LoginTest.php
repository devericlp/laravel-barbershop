<?php

use App\Models\Customer;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

test('login page renders for guests', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Entrar')
        ->assertSee('Lembrar de mim');
});

test('authenticated users are redirected from login to dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect(route('dashboard'));
});

test('users can log in with valid credentials', function () {
    $user = User::factory()->create();

    Livewire::test('pages::auth.auth-login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('each role lands on its own start page and the tenant goes to the session', function () {
    $admin = User::factory()->admin()->create();
    $customer = Customer::factory()->create();
    app(CurrentTenant::class)->set(null);

    Livewire::test('pages::auth.auth-login')
        ->set('email', $admin->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('tenants.index'));

    expect(session('tenant_id'))->toBeNull();

    Livewire::test('pages::auth.auth-login')
        ->set('email', $customer->user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('bookings.create'));

    expect(session('tenant_id'))->toBeNull();

    $manager = inTenant($this->tenant, fn () => User::factory()->manager()->create());

    Livewire::test('pages::auth.auth-login')
        ->set('email', $manager->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('dashboard'));

    expect(session('tenant_id'))->toBe($this->tenant->id);
});

test('a global customer logs in even when a barbershop where they have a record is inactive', function () {
    $customer = Customer::factory()->create();
    $this->tenant->update(['is_active' => false]);
    app(CurrentTenant::class)->set(null);

    Livewire::test('pages::auth.auth-login')
        ->set('email', $customer->user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('bookings.create'));
});

test('the login page keeps the booking state as the return address', function () {
    $this->get(route('login', ['barbershop' => $this->tenant->slug, 'booking' => 'estado']))->assertOk();

    expect(session('url.intended'))->toBe(route('public-bookings.create', ['barbershop' => $this->tenant->slug, 'booking' => 'estado']));
});

test('users of an inactive tenant cannot log in', function () {
    $user = User::factory()->create();
    $this->tenant->update(['is_active' => false]);

    Livewire::test('pages::auth.auth-login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email' => 'O acesso desta barbearia está desativado.']);

    $this->assertGuest();
});

test('users are redirected to the intended url after login', function () {
    $user = User::factory()->create();

    $this->get(route('dashboard'))->assertRedirect(route('login'));

    Livewire::test('pages::auth.auth-login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('dashboard'));
});

test('users cannot log in with an invalid password', function () {
    $user = User::factory()->create();

    Livewire::test('pages::auth.auth-login')
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email' => 'Essas credenciais não correspondem aos nossos registros.'])
        ->assertSet('email', $user->email)
        ->assertSet('password', '');

    $this->assertGuest();
});

test('email and password are validated in portuguese', function () {
    Livewire::test('pages::auth.auth-login')
        ->set('email', '')
        ->set('password', '')
        ->call('login')
        ->assertHasErrors([
            'email' => 'O campo e-mail é obrigatório.',
            'password' => 'O campo senha é obrigatório.',
        ]);

    Livewire::test('pages::auth.auth-login')
        ->set('email', 'invalid')
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email' => 'O campo e-mail deve ser um endereço de e-mail válido.']);
});

test('login is blocked after five failed attempts', function () {
    $user = User::factory()->create();

    $component = Livewire::test('pages::auth.auth-login')->set('email', $user->email);

    foreach (range(1, 5) as $attempt) {
        $component->set('password', 'wrong-password')->call('login');
    }

    $component->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    expect($component->errors()->first('email'))->toStartWith('Muitas tentativas de login.');

    $this->assertGuest();
});

test('successful login clears failed attempts', function () {
    $user = User::factory()->create();

    $component = Livewire::test('pages::auth.auth-login')->set('email', $user->email);

    foreach (range(1, 4) as $attempt) {
        $component->set('password', 'wrong-password')->call('login');
    }

    $component->set('password', 'password')->call('login')->assertHasNoErrors();

    expect(RateLimiter::attempts(strtolower($user->email).'|127.0.0.1'))->toBe(0);
});

test('remember me issues the remember cookie', function () {
    $user = User::factory()->create();

    Livewire::test('pages::auth.auth-login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->set('remember', true)
        ->call('login');

    expect($user->fresh()->remember_token)->not->toBeNull()
        ->and(cookie()->getQueuedCookies())->not->toBeEmpty();
});
