<?php

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->manager = User::factory()->manager()->create();
    $this->actingAs($this->manager);
});

test('only users with permission open the customers page', function () {
    $this->get(route('customers.index'))->assertOk();

    $this->actingAs(User::factory()->professional()->create())
        ->get(route('customers.index'))
        ->assertForbidden();
});

test('lists customers of the tenant with and without account', function () {
    Customer::factory()->withoutUser()->create(['name' => 'Sem Conta', 'phone' => '11912345678']);
    Customer::factory()->named('Com Conta')->create();
    inTenant(Tenant::factory()->create(), fn () => Customer::factory()->withoutUser()->create(['name' => 'Cliente Alheio']));

    $this->get(route('customers.index'))
        ->assertOk()
        ->assertSee('Sem Conta')
        ->assertSee('(11) 91234-5678')
        ->assertSee('Com Conta')
        ->assertDontSee('Cliente Alheio');
});

test('searches by name or phone and filters by account', function () {
    Customer::factory()->withoutUser()->create(['name' => 'Ana', 'phone' => '11911112222']);
    Customer::factory()->named('Bruno')->create(['phone' => '11933334444']);

    $search = fn (string $term) => Livewire::test('pages::customers.customer-index')
        ->set('searchInput', $term)
        ->call('applySearch')
        ->get('customers')
        ->pluck('name')
        ->all();

    expect($search('Ana'))->toBe(['Ana'])
        ->and($search('3333'))->toBe(['Bruno']);

    $component = Livewire::test('pages::customers.customer-index')
        ->set('filters.account', 'without')
        ->call('applyFilters');

    expect($component->get('customers')->pluck('name')->all())->toBe(['Ana']);
});

test('creates a customer with only name and mobile', function () {
    Livewire::test('customer-form')
        ->call('open')
        ->set('name', 'Carlos Lima')
        ->set('phone', '(11) 98888-7777')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('customer-created');

    expect(Customer::sole())
        ->name->toBe('Carlos Lima')
        ->phone->toBe('11988887777')
        ->user_id->toBeNull();
});

test('a repeated mobile is refused', function () {
    Customer::factory()->withoutUser()->create(['phone' => '11988887777']);

    Livewire::test('customer-form')
        ->call('open')
        ->set('name', 'Outro')
        ->set('phone', '(11) 98888-7777')
        ->call('save')
        ->assertHasErrors('phone');
});

test('editing a record with an account keeps the link and the account name', function () {
    $customer = Customer::factory()->named('João Silva')->create();

    Livewire::test('customer-form')
        ->call('open', $customer->id)
        ->set('name', 'João')
        ->call('save')
        ->assertHasNoErrors();

    expect($customer->fresh())
        ->user_id->toBe($customer->user_id)
        ->name->toBe('João')
        ->and($customer->user->fresh()->name)->toBe('João Silva');
});

test('the linked account email of a global customer is listed', function () {
    $customer = Customer::factory()->create();

    $this->get(route('customers.index'))->assertOk()->assertSee($customer->user->email);
});

test('deletes a customer without account and bookings', function () {
    $customer = Customer::factory()->withoutUser()->create();

    Livewire::test('pages::customers.customer-index')
        ->call('confirmDelete', $customer->id)
        ->call('delete');

    expect(Customer::find($customer->id))->toBeNull();
});

test('keeps a customer with bookings', function () {
    $customer = Customer::factory()->withoutUser()->create();
    Booking::factory()->for($customer)->create();

    Livewire::test('pages::customers.customer-index')
        ->call('confirmDelete', $customer->id)
        ->call('delete');

    expect(Customer::find($customer->id))->not->toBeNull();
});

test('lists customers without one query per row', function () {
    $queriesFor = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test('pages::customers.customer-index');
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    Customer::factory()->create();
    $queriesFor();
    $fewRows = $queriesFor();
    Customer::factory()->count(3)->create();
    Customer::factory()->withoutUser()->count(2)->create();

    expect($queriesFor())->toBe($fewRows);
});
