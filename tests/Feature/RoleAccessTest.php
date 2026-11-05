<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\User;

function userWithRole(UserRole $role): User
{
    return match ($role) {
        UserRole::Admin => User::factory()->admin()->create(),
        UserRole::Manager => User::factory()->manager()->create(),
        UserRole::Professional => Professional::factory()->create()->user,
        UserRole::Customer => Customer::factory()->create()->user,
    };
}

test('each role reaches only its own pages', function (UserRole $role, array $allowed) {
    $user = userWithRole($role);

    $routes = ['tenants.index', 'access-control.index', 'dashboard', 'services.index', 'professionals.index', 'users.index', 'bookings.index', 'bookings.create', 'my-bookings.index'];

    foreach ($routes as $route) {
        $this->actingAs($user)
            ->get(route($route))
            ->assertStatus(in_array($route, $allowed, true) ? 200 : 403);
    }
})->with([
    'admin' => [UserRole::Admin, ['tenants.index', 'access-control.index']],
    'manager' => [UserRole::Manager, ['dashboard', 'services.index', 'professionals.index', 'users.index', 'bookings.index']],
    'professional' => [UserRole::Professional, ['bookings.index']],
    'customer' => [UserRole::Customer, ['bookings.create', 'my-bookings.index']],
]);

test('the home page sends each role to its start page', function (UserRole $role, string $route) {
    $this->actingAs(userWithRole($role))
        ->get(route('home'))
        ->assertRedirect(route($route));
})->with([
    [UserRole::Admin, 'tenants.index'],
    [UserRole::Manager, 'dashboard'],
    [UserRole::Professional, 'bookings.index'],
    [UserRole::Customer, 'bookings.create'],
]);

test('guests are sent to the login page', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('every role can open the profile page', function (UserRole $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('profile.edit'))
        ->assertOk();
})->with(UserRole::cases());
