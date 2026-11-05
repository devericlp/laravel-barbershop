<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 07:00'));
    $this->manager = User::factory()->manager()->create();
    $this->other = Tenant::factory()->create();
    $this->foreign = inTenant($this->other, fn () => [
        'service' => Service::factory()->create(['name' => 'Serviço alheio']),
        'professional' => Professional::factory()->named('Profissional Alheio')->create(),
        'customer' => Customer::factory()->named('Cliente Alheio')->create(),
        'booking' => Booking::factory()->create(['starts_at' => CarbonImmutable::parse('2026-11-02 10:00')]),
    ]);
});

test('queries only see records of the current tenant', function () {
    Service::factory()->create();

    expect(Service::count())->toBe(1)
        ->and(Professional::count())->toBe(0)
        ->and(Customer::count())->toBe(0)
        ->and(Booking::count())->toBe(0)
        ->and(User::pluck('id')->all())->toBe([$this->manager->id]);

    app(CurrentTenant::class)->set(null);

    expect(Service::count())->toBe(3);
});

test('new records receive the current tenant', function () {
    $service = Service::create(['name' => 'Corte', 'price_cents' => 4500, 'duration_minutes' => 30, 'is_active' => true]);

    expect($service->tenant_id)->toBe($this->tenant->id);
});

test('a manager cannot open records of another tenant by id', function () {
    $this->actingAs($this->manager);

    Livewire::test('professional-form')
        ->call('open', $this->foreign['professional']->id)
        ->assertNotFound();
});

test('pages list only the tenant data', function () {
    Professional::factory()->named('Profissional Daqui')->create();

    $this->actingAs($this->manager)
        ->get(route('professionals.index'))
        ->assertOk()
        ->assertSee('Profissional Daqui')
        ->assertDontSee('Profissional Alheio');

    $this->actingAs($this->manager)
        ->get(route('bookings.index', ['date' => '2026-11-02']))
        ->assertOk()
        ->assertDontSee('Cliente Alheio');
});

test('the agenda cannot change a booking of another tenant', function () {
    $this->actingAs($this->manager);

    Livewire::test('pages::bookings.booking-index')
        ->call('cancel', $this->foreign['booking']->id)
        ->assertNotFound();

    expect(inTenant($this->other, fn () => $this->foreign['booking']->fresh()->status))->toBe(BookingStatus::Scheduled);
});

test('forms reject ids from another tenant', function () {
    $this->actingAs($this->manager);
    $service = Service::factory()->create();
    $professional = bookableProfessional($service);

    Livewire::test('booking-form')
        ->call('open', null, '2026-11-02')
        ->set('customerId', $this->foreign['customer']->id)
        ->set('serviceId', $service->id)
        ->set('professionalId', $professional->id)
        ->set('time', '09:00')
        ->call('save')
        ->assertHasErrors(['customerId']);

    Livewire::test('professional-form')
        ->call('open')
        ->set('name', 'Novo')
        ->set('email', 'novo@email.com')
        ->set('password', 'password')
        ->call('continue')
        ->set('serviceIds', [(string) $this->foreign['service']->id])
        ->call('continue')
        ->assertHasErrors(['serviceIds.0']);
});

test('the middleware applies the tenant of the signed in user', function () {
    $foreignManager = inTenant($this->other, fn () => User::factory()->manager()->create());

    app(CurrentTenant::class)->set(null);

    $this->actingAs($foreignManager)
        ->get(route('professionals.index'))
        ->assertOk()
        ->assertSee('Profissional Alheio');

    expect(session('tenant_id'))->toBe($this->other->id);
});

test('users of an inactive tenant are signed out', function () {
    $this->tenant->update(['is_active' => false]);

    $this->actingAs($this->manager)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
