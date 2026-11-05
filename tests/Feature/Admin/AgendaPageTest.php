<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 07:00'));
    $this->actingAs(User::factory()->manager()->create());
});

test('shows the bookings of the selected day with customer data', function () {
    $customer = Customer::factory()->named('Carlos Lima')->create(['phone' => '11912345678']);
    Booking::factory()->for($customer)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 10:00')]);
    Booking::factory()->create(['starts_at' => CarbonImmutable::parse('2026-11-03 10:00')]);

    $this->get(route('bookings.index', ['date' => '2026-11-02']))
        ->assertOk()
        ->assertSee('Carlos Lima')
        ->assertSee('(11) 91234-5678')
        ->assertSee('10:00 – 10:30');

    Livewire::test('pages::bookings.booking-index', ['date' => '2026-11-02'])
        ->assertCount('bookings', 1)
        ->set('view', 'week')
        ->assertCount('bookings', 2);
});

test('filters by professional', function () {
    $booking = Booking::factory()->create(['starts_at' => CarbonImmutable::parse('2026-11-02 10:00')]);
    Booking::factory()->create(['starts_at' => CarbonImmutable::parse('2026-11-02 11:00')]);

    Livewire::test('pages::bookings.booking-index')
        ->set('filters.professional', (string) $booking->professional_id)
        ->assertCount('bookings', 2)
        ->call('applyFilters')
        ->assertCount('bookings', 1);
});

test('changes the status of a booking', function (string $method, BookingStatus $status) {
    $booking = Booking::factory()->create(['starts_at' => CarbonImmutable::parse('2026-11-02 10:00')]);

    Livewire::test('pages::bookings.booking-index')->call($method, $booking->id);

    expect($booking->fresh()->status)->toBe($status);
})->with([
    ['complete', BookingStatus::Completed],
    ['cancel', BookingStatus::Cancelled],
    ['markAsNoShow', BookingStatus::NoShow],
]);

test('creates a booking from the admin form', function () {
    $service = Service::factory()->create();
    $professional = bookableProfessional($service);

    $customer = Customer::factory()->named('Pedro Alves')->create();

    Livewire::test('booking-form')
        ->call('open', null, '2026-11-02')
        ->set('customerId', $customer->id)
        ->set('serviceId', $service->id)
        ->set('professionalId', $professional->id)
        ->assertSet('times', fn (array $times) => in_array('09:00', $times, true))
        ->set('time', '09:00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('booking-saved');

    expect(Booking::sole()->customer->name)->toBe('Pedro Alves');
});

test('edits a booking from the admin form', function () {
    $service = Service::factory()->create();
    $professional = bookableProfessional($service);
    $booking = Booking::factory()->for($service)->for($professional)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 09:00')]);

    Livewire::test('booking-form')
        ->call('open', $booking->id)
        ->assertSet('time', '09:00')
        ->set('time', '10:00')
        ->call('save')
        ->assertHasNoErrors();

    expect($booking->fresh()->starts_at->format('H:i'))->toBe('10:00');
});

test('clearing the professional filter shows everyone again', function () {
    Booking::factory()->count(2)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 10:00')]);

    Livewire::test('pages::bookings.booking-index')
        ->set('filters.professional', '')
        ->call('applyFilters')
        ->assertSet('appliedFilters', [])
        ->assertCount('bookings', 2);
});

test('searches by customer name or phone', function () {
    $ana = Customer::factory()->named('Ana Paula')->create(['phone' => '11987654321']);
    Booking::factory()->for($ana)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 09:00')]);
    Booking::factory()->for(Customer::factory()->named('Bruno Costa')->create(['phone' => '11911112222']))->create(['starts_at' => CarbonImmutable::parse('2026-11-02 10:00')]);

    Livewire::test('pages::bookings.booking-index')
        ->set('searchInput', 'ana')
        ->call('applySearch')
        ->assertCount('bookings', 1)
        ->set('searchInput', '98765-4321')
        ->call('applySearch')
        ->assertCount('bookings', 1)
        ->set('searchInput', 'ninguém')
        ->call('applySearch')
        ->assertCount('bookings', 0)
        ->assertSee('Nenhum agendamento encontrado')
        ->call('clearFilters')
        ->assertCount('bookings', 2);
});

test('filters by status and service showing removable chips', function () {
    $booking = Booking::factory()->completed()->create(['starts_at' => CarbonImmutable::parse('2026-11-02 09:00')]);
    Booking::factory()->create(['starts_at' => CarbonImmutable::parse('2026-11-02 10:00')]);

    Livewire::test('pages::bookings.booking-index')
        ->set('filters.status', ['completed', 'cancelled'])
        ->set('filters.service', (string) $booking->service_id)
        ->call('applyFilters')
        ->assertHasNoErrors()
        ->assertCount('bookings', 1)
        ->assertSee('Status: Concluído')
        ->assertSee('Status: Cancelado')
        ->assertSee('Limpar filtros')
        ->call('removeFilter', 'status:completed')
        ->assertSet('appliedFilters.status', ['cancelled'])
        ->assertSet('filters.status', ['cancelled'])
        ->assertCount('bookings', 0)
        ->call('removeFilter', 'status:cancelled')
        ->assertSet('appliedFilters', ['service' => (string) $booking->service_id])
        ->call('clearFilters')
        ->assertSet('appliedFilters', [])
        ->assertCount('bookings', 2);
});

test('rejects unknown statuses in the filter', function () {
    Livewire::test('pages::bookings.booking-index')
        ->set('filters.status', ['bogus'])
        ->call('applyFilters')
        ->assertHasErrors(['filters.status.0'])
        ->assertSet('appliedFilters', []);
});

test('ignores unknown sort columns and page sizes', function () {
    Livewire::withQueryParams(['sort' => 'password'])
        ->test('pages::bookings.booking-index')
        ->assertSet('sortBy', 'starts_at')
        ->set('perPage', 999)
        ->assertSet('perPage', 10);
});

test('sorts by customer and paginates', function () {
    foreach (['Carlos', 'Ana', 'Bruno'] as $index => $name) {
        Booking::factory()
            ->for(Customer::factory()->named($name)->create())
            ->create(['starts_at' => CarbonImmutable::parse('2026-11-02 09:00')->addHours($index)]);
    }

    $component = Livewire::test('pages::bookings.booking-index')->call('sort', 'customer');

    expect($component->get('bookings')->pluck('customer.name')->all())->toBe(['Ana', 'Bruno', 'Carlos']);

    $component->call('sort', 'customer');

    expect($component->get('bookings')->pluck('customer.name')->all())->toBe(['Carlos', 'Bruno', 'Ana']);

    Booking::factory()->count(10)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 15:00')]);

    Livewire::test('pages::bookings.booking-index')
        ->assertSee('Exibindo 1–10 de 13')
        ->call('gotoPage', 2)
        ->assertCount('bookings', 3)
        ->set('perPage', 25)
        ->assertCount('bookings', 13);
});

test('hides optional columns', function () {
    Booking::factory()->create(['starts_at' => CarbonImmutable::parse('2026-11-02 09:00'), 'price_cents' => 12345]);

    Livewire::test('pages::bookings.booking-index')
        ->assertSee('R$ 123,45')
        ->set('columns', ['service', 'professional', 'status'])
        ->assertDontSee('R$ 123,45');
});

test('lists bookings without one query per row', function () {
    $queriesFor = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test('pages::bookings.booking-index', ['date' => '2026-11-02']);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    Booking::factory()->create(['starts_at' => CarbonImmutable::parse('2026-11-02 09:00')]);
    $queriesFor();
    $fewRows = $queriesFor();
    Booking::factory()->count(4)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 11:00')]);

    expect($queriesFor())->toBe($fewRows);
});
