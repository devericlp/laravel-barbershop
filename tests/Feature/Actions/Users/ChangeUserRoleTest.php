<?php

use App\Actions\Users\ChangeUserRole;
use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Exceptions\RoleChangeException;
use App\Exceptions\RoleNotEnabledException;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 07:00'));
    $this->manager = User::factory()->manager()->create();
});

test('switching the main role replaces the previous one', function () {
    $user = User::factory()->manager()->create();

    app(ChangeUserRole::class)->handle($user, UserRole::Professional);

    expect($user->fresh()->userRoles())->toBe([UserRole::Professional])
        ->and($user->fresh()->professional->is_active)->toBeTrue();
});

test('the customer main role links the customer that already has the mobile', function () {
    $customer = Customer::factory()->withoutUser()->create(['name' => 'João', 'phone' => '11912345678']);
    $user = User::factory()->manager()->create(['name' => 'João Silva']);

    app(ChangeUserRole::class)->handle($user, UserRole::Customer, '(11) 91234-5678');

    expect($customer->fresh())->user_id->toBe($user->id)->name->toBe('João Silva')
        ->and($user->fresh()->userRoles())->toBe([UserRole::Customer]);
});

test('the customer main role requires the mobile', function () {
    app(ChangeUserRole::class)->handle(User::factory()->manager()->create(), UserRole::Customer);
})->throws(ValidationException::class);

test('a global customer account cannot become staff', function () {
    $customer = Customer::factory()->create();

    expect(fn () => app(ChangeUserRole::class)->handle($customer->user, UserRole::Manager))
        ->toThrow(RoleNotEnabledException::class);

    expect($customer->user->fresh()->userRoles())->toBe([UserRole::Customer]);
});

test('leaving and coming back to professional reuses the same record', function () {
    $user = User::factory()->manager()->create();
    $change = app(ChangeUserRole::class);

    $change->handle($user, UserRole::Professional);
    $professional = $user->fresh()->professional;

    $change->handle($user->fresh(), UserRole::Manager);

    expect($professional->fresh()->is_active)->toBeFalse();

    $change->handle($user->fresh(), UserRole::Professional);

    expect(Professional::count())->toBe(1)
        ->and($professional->fresh()->is_active)->toBeTrue();
});

test('leaving professional with upcoming bookings is refused', function () {
    $professional = Professional::factory()->create();
    Booking::factory()->for($professional)->create([
        'status' => BookingStatus::Scheduled,
        'starts_at' => CarbonImmutable::parse('2026-11-03 10:00'),
    ]);

    app(ChangeUserRole::class)->handle($professional->user, UserRole::Manager);
})->throws(RoleChangeException::class);

test('the barbershop keeps at least one manager', function () {
    app(ChangeUserRole::class)->handle($this->manager, UserRole::Professional);
})->throws(RoleChangeException::class);

test('a role not enabled in the barbershop is refused', function () {
    $this->tenant->roles()->sync(Role::where('name', 'manager')->pluck('id'));

    app(ChangeUserRole::class)->handle(User::factory()->manager()->create(), UserRole::Professional);
})->throws(RoleNotEnabledException::class);
