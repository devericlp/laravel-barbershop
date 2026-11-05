<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->manager = User::factory()->manager()->create(['name' => 'Gerente Daqui']);
    $this->actingAs($this->manager);
});

test('lists only the tenant staff filtered by role', function () {
    $professional = Professional::factory()->named('Bruno Navalha')->create();
    $professional->user->assignRole(UserRole::Customer->value);
    Customer::factory()->create(['user_id' => $professional->user_id, 'phone' => '11912345678']);
    Customer::factory()->named('Ana Cliente')->create();
    inTenant(Tenant::factory()->create(), fn () => User::factory()->manager()->create(['name' => 'Gerente Alheio']));

    $this->get(route('users.index'))
        ->assertOk()
        ->assertSee('Bruno Navalha')
        ->assertSee('(11) 91234-5678')
        ->assertDontSee('Ana Cliente')
        ->assertDontSee('Gerente Alheio');

    Livewire::test('pages::users.user-index')
        ->set('filters.role', 'professional')
        ->call('applyFilters')
        ->assertSee('Bruno Navalha')
        ->assertDontSee('Gerente Daqui')
        ->call('removeFilter', 'role')
        ->set('searchInput', '91234')
        ->call('applySearch')
        ->assertSee('Bruno Navalha')
        ->assertDontSee('Gerente Daqui');
});

test('lists users without one query per row', function () {
    $queriesFor = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test('pages::users.user-index');
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    Customer::factory()->create();
    Professional::factory()->create();
    $queriesFor();
    $fewRows = $queriesFor();
    Customer::factory()->count(3)->create();
    Professional::factory()->count(2)->create();

    expect($queriesFor())->toBe($fewRows);
});

test('the form does not create customer accounts', function () {
    $form = Livewire::test('user-form')->call('open', 'customer');

    expect($form->get('roleOptions'))->not->toContain(UserRole::Customer);

    $form->set('name', 'Bruno Cliente')
        ->set('email', 'bruno@email.com')
        ->set('password', 'password')
        ->call('save')
        ->assertHasErrors('role');

    expect(User::where('email', 'bruno@email.com')->exists())->toBeFalse();
});

test('creates another manager', function () {
    Livewire::test('user-form')
        ->call('open', 'manager')
        ->set('name', 'Outra Gerente')
        ->set('email', 'outra@email.com')
        ->set('password', 'password')
        ->call('save')
        ->assertHasNoErrors();

    expect(User::withRole(UserRole::Manager)->count())->toBe(2);
});

test('edits a user keeping the password when left blank', function () {
    $user = User::factory()->manager()->create(['name' => 'Ana']);
    $password = $user->password;

    Livewire::test('user-form')
        ->call('open', null, $user->id)
        ->assertSet('name', 'Ana')
        ->set('name', 'Ana Paula')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Ana Paula')
        ->and($user->fresh()->password)->toBe($password);
});
test('refuses to delete the own account', function () {
    User::factory()->manager()->create();

    Livewire::test('pages::users.user-index')
        ->call('confirmDelete', $this->manager->id)
        ->call('delete');

    expect($this->manager->fresh())->not->toBeNull();
});

test('only managers can use the user form', function () {
    $this->actingAs(Customer::factory()->create()->user);

    Livewire::test('user-form')->call('open', 'manager')->assertForbidden();
});

test('the form accepts a single role', function () {
    expect(fn () => Livewire::test('user-form')
        ->call('open', 'manager')
        ->set('role', ['manager', 'professional']))->toThrow(TypeError::class);
});

test('changing the main role to professional opens the professional wizard', function () {
    $user = User::factory()->manager()->create();

    Livewire::test('user-form')
        ->call('open', null, $user->id)
        ->assertSet('role', 'manager')
        ->set('role', 'professional')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('open-professional-form', professionalId: $user->fresh()->professional->id, step: 2);

    expect($user->fresh()->userRoles())->toBe([UserRole::Professional]);
});

test('links a manager as customer and unlinks keeping the record', function () {
    $user = User::factory()->manager()->create();

    Livewire::test('pages::users.user-index')
        ->call('confirmLinkCustomer', $user->id)
        ->set('linkPhone', '(11) 97777-6666')
        ->call('linkCustomer')
        ->assertHasNoErrors();

    $customer = $user->fresh()->customer;

    expect($customer->phone)->toBe('11977776666')
        ->and($user->fresh()->userRoles())->toBe([UserRole::Manager, UserRole::Customer]);

    Livewire::test('pages::users.user-index')
        ->call('confirmUnlinkCustomer', $user->id)
        ->call('unlinkCustomer');

    expect($customer->fresh()->user_id)->toBeNull()
        ->and($user->fresh()->userRoles())->toBe([UserRole::Manager]);
});

test('linking with the mobile of a customer that has another account is refused on the field', function () {
    Customer::factory()->create(['phone' => '11977776666']);
    $user = User::factory()->manager()->create();

    Livewire::test('pages::users.user-index')
        ->call('confirmLinkCustomer', $user->id)
        ->set('linkPhone', '(11) 97777-6666')
        ->call('linkCustomer')
        ->assertHasErrors('linkPhone');

    expect($user->fresh()->customer)->toBeNull();
});

test('linking as customer requires its own permission', function () {
    Role::findByName('manager')->revokePermissionTo('users.link-customer');
    $user = User::factory()->manager()->create();

    Livewire::test('pages::users.user-index')
        ->assertDontSeeHtml('confirmLinkCustomer(')
        ->call('confirmLinkCustomer', $user->id)
        ->assertForbidden();
});

test('refusals when changing the role appear on the role field', function () {
    Livewire::test('user-form')
        ->call('open', null, $this->manager->id)
        ->set('role', 'customer')
        ->call('save')
        ->assertHasErrors('role');

    expect($this->manager->fresh()->userRoles())->toBe([UserRole::Manager]);
});

test('deleting a professional who has other roles keeps the account', function () {
    $professional = Professional::factory()->create();
    $professional->user->assignRole(UserRole::Manager->value);

    Livewire::test('pages::professionals.professional-index')
        ->call('confirmDelete', $professional->id)
        ->call('delete');

    expect(Professional::find($professional->id))->toBeNull()
        ->and($professional->user->fresh()->userRoles())->toBe([UserRole::Manager]);
});
