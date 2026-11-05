<?php

use App\Http\Middleware\EnsureRoleIsSelected;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 07:00'));
    $this->service = Service::factory()->create(['name' => 'Corte', 'price_cents' => 4500, 'duration_minutes' => 30]);
    $this->professional = bookableProfessional($this->service);
    $this->customer = Customer::factory()->named('João Silva')->create();
    $this->actingAs($this->customer->user);
});

function customerBooking(): Testable
{
    return Livewire::withQueryParams(['barbershop' => test()->tenant->slug])->test('pages::bookings.booking-create');
}

function bookingAtProfessionalStep(int $serviceId, string $date = '2026-11-02'): Testable
{
    return customerBooking()
        ->call('selectService', $serviceId)
        ->call('selectDate', $date);
}

function bookingAtTimeStep(int $serviceId, int $professionalId, string $date = '2026-11-02'): Testable
{
    return bookingAtProfessionalStep($serviceId, $date)->call('selectProfessional', $professionalId);
}

function publicBooking(): Testable
{
    return Livewire::test('pages::bookings.booking-create');
}

test('the booking page lists only bookable services', function () {
    Service::factory()->inactive()->create(['name' => 'Pigmentação']);
    Service::factory()->create(['name' => 'Sem profissional']);

    $this->get(route('bookings.create', ['barbershop' => $this->tenant->slug]))
        ->assertOk()
        ->assertSee('Corte')
        ->assertSee('R$ 45,00')
        ->assertDontSee('Pigmentação')
        ->assertDontSee('Sem profissional');
});

test('a manager cannot open the internal booking page', function () {
    $this->actingAs(User::factory()->manager()->create())
        ->get(route('bookings.create'))
        ->assertForbidden();
});

test('a visitor opening the internal booking page goes to the public one', function () {
    $this->post(route('logout'));

    $this->get(route('bookings.create'))->assertRedirect(route('public-bookings.create'));
});

test('a logged customer opening the public booking page goes to the internal one', function () {
    $this->get(route('public-bookings.create'))->assertRedirect(route('bookings.create'));
});

test('a logged customer sees the page without the app layout and a close button to the home page', function () {
    $this->get(route('bookings.create', ['barbershop' => $this->tenant->slug]))
        ->assertOk()
        ->assertDontSee('data-flux-sidebar', false)
        ->assertSee($this->tenant->name)
        ->assertSee('aria-label="Fechar"', false)
        ->assertSee('href="'.$this->customer->user->homeUrl().'"', false);
});

test('a visitor opens the public page without the app layout and without the close button', function () {
    $this->post(route('logout'));

    $this->get(route('public-bookings.create'))
        ->assertOk()
        ->assertSee('Escolha a barbearia')
        ->assertSee($this->tenant->name)
        ->assertDontSee('data-flux-sidebar', false)
        ->assertDontSee('aria-label="Fechar"', false);
});

test('a logged user who is not a customer opens the public page with the close button to the home page', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)
        ->get(route('public-bookings.create'))
        ->assertOk()
        ->assertSee('Escolha a barbearia')
        ->assertDontSee('data-flux-sidebar', false)
        ->assertSee('href="'.$manager->homeUrl().'"', false);
});

test('each choice advances: service, date, professional, time and confirmation', function () {
    customerBooking()
        ->assertSet('step', 1)
        ->assertSee('Escolha um serviço')
        ->call('selectService', $this->service->id)
        ->assertSet('step', 2)
        ->assertSee('Escolha a data')
        ->call('selectDate', '2026-11-02')
        ->assertSet('step', 3)
        ->assertSee('Disponíveis em segunda-feira, 2 de novembro')
        ->call('selectProfessional', $this->professional->id)
        ->assertSet('step', 4)
        ->assertSee($this->professional->name.' - ')
        ->call('selectTime', '09:30')
        ->assertSet('step', 5)
        ->assertSee('Revise seu agendamento')
        ->assertSee('Confirmar agendamento')
        ->call('confirm')
        ->assertSet('step', 6)
        ->assertSee('Agendamento confirmado!');

    $booking = Booking::sole();

    expect($booking->starts_at->format('Y-m-d H:i'))->toBe('2026-11-02 09:30')
        ->and($booking->professional_id)->toBe($this->professional->id)
        ->and($booking->customer_id)->toBe($this->customer->id);
});

test('choices cannot be changed without going through the select actions', function () {
    customerBooking()->set('serviceId', $this->service->id);
})->throws(CannotUpdateLockedPropertyException::class);

test('lists only professionals with a free time on the chosen date', function () {
    $tuesday = bookableProfessional($this->service, dayOfWeek: 2);
    $busy = bookableProfessional($this->service, [['09:00', '09:30']]);
    Booking::factory()->for($busy)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 09:00')]);

    $ids = bookingAtProfessionalStep($this->service->id)->get('professionals')->pluck('id')->all();

    expect($ids)->toBe([$this->professional->id])
        ->and($ids)->not->toContain($tuesday->id, $busy->id);
});

test('a date without any free professional keeps the customer on the date step', function () {
    customerBooking()
        ->call('selectService', $this->service->id)
        ->call('selectDate', '2026-11-03')
        ->assertSet('step', 2)
        ->assertSet('date', null)
        ->assertHasErrors(['date'])
        ->call('selectDate', '2027-01-04')
        ->assertSet('step', 2)
        ->call('selectDate', 'invalid')
        ->assertSet('step', 2);
});

test('the time step shows only the free times of the professional', function () {
    Booking::factory()->for($this->professional)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 10:00')]);

    $component = bookingAtTimeStep($this->service->id, $this->professional->id)
        ->assertSee('10:30')
        ->assertDontSee('10:00')
        ->assertDontSee('09:45');

    expect($component->get('times'))->toBe(['09:00', '09:15', '09:30', '10:30', '10:45', '11:00', '11:15', '11:30']);

    $component->call('selectTime', '10:00')
        ->assertHasErrors(['time'])
        ->assertSet('step', 4)
        ->assertSet('time', null);
});

test('going back keeps the choices and choosing the same option again advances', function () {
    bookingAtTimeStep($this->service->id, $this->professional->id)
        ->call('selectTime', '10:00')
        ->call('goBack')
        ->call('goBack')
        ->call('goBack')
        ->call('goBack')
        ->assertSet('step', 1)
        ->call('goBack')
        ->assertSet('step', 1)
        ->call('selectService', $this->service->id)
        ->call('selectDate', '2026-11-02')
        ->assertSet('professionalId', $this->professional->id)
        ->call('selectProfessional', $this->professional->id)
        ->assertSet('time', '10:00')
        ->assertSet('step', 4);
});

test('changing the date clears the time and drops a professional without free times on it', function () {
    $mondayOnly = $this->professional;
    $everyDay = bookableProfessional($this->service);
    $everyDay->workingHours()->create(['day_of_week' => 2, 'starts_at' => '09:00', 'ends_at' => '12:00']);

    bookingAtTimeStep($this->service->id, $mondayOnly->id)
        ->call('selectTime', '10:00')
        ->call('goBack')
        ->call('goBack')
        ->call('goBack')
        ->call('selectDate', '2026-11-03')
        ->assertSet('time', null)
        ->assertSet('professionalId', null)
        ->call('selectProfessional', $everyDay->id)
        ->assertSet('step', 4);
});

test('changing the professional keeps the time only when it is still free', function () {
    $other = bookableProfessional($this->service);
    Booking::factory()->for($other)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 10:00')]);

    bookingAtTimeStep($this->service->id, $this->professional->id)
        ->call('selectTime', '10:00')
        ->call('goBack')
        ->call('goBack')
        ->call('selectProfessional', $other->id)
        ->assertSet('time', null);
});

test('shows a message when no professional performs the service', function () {
    $component = customerBooking()->call('selectService', $this->service->id);

    $this->professional->services()->detach($this->service);
    bookableProfessional(Service::factory()->create());

    $component->call('selectDate', '2026-11-02')
        ->assertSet('step', 2)
        ->assertSee('Nenhum profissional disponível nesta data.');
});

test('sends the customer back to the time step when the slot was taken', function () {
    $component = bookingAtTimeStep($this->service->id, $this->professional->id)->call('selectTime', '09:30');

    Booking::factory()->for($this->professional)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 09:30')]);

    $component->call('confirm')
        ->assertHasErrors(['time'])
        ->assertSet('step', 4)
        ->assertSet('time', null)
        ->assertSet('serviceId', $this->service->id)
        ->assertSet('professionalId', $this->professional->id);

    expect(Booking::count())->toBe(1);
});

test('confirming twice creates a single booking', function () {
    bookingAtTimeStep($this->service->id, $this->professional->id)
        ->call('selectTime', '09:30')
        ->call('confirm')
        ->call('confirm');

    expect(Booking::count())->toBe(1);
});

test('does not show other customers data', function () {
    $other = Customer::factory()->named('Cliente Sigiloso')->create();
    Booking::factory()->for($other)->for($this->professional)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 09:00')]);

    bookingAtTimeStep($this->service->id, $this->professional->id)->assertDontSee('Cliente Sigiloso');
});

test('a professional booking as customer does not see themselves as an option', function () {
    $other = bookableProfessional($this->service);
    $this->professional->user->assignRole('customer');
    Customer::factory()->create(['user_id' => $this->professional->user_id]);

    $this->actingAs($this->professional->user->fresh())
        ->withSession([EnsureRoleIsSelected::SESSION_KEY => 'customer']);

    $component = bookingAtProfessionalStep($this->service->id);

    expect($component->get('professionals')->pluck('id')->all())->toBe([$other->id]);
});

describe('public booking', function () {
    beforeEach(function () {
        $this->post(route('logout'));
        app(CurrentTenant::class)->set(null);
    });

    test('a visitor chooses the barbershop first and books with name and mobile', function () {
        $component = publicBooking()
            ->assertSet('step', 0)
            ->call('selectBarbershop', $this->tenant->id)
            ->assertSet('step', 1)
            ->call('selectService', $this->service->id)
            ->call('selectDate', '2026-11-02')
            ->call('selectProfessional', $this->professional->id)
            ->call('selectTime', '09:30')
            ->assertSet('step', 5)
            ->call('confirm')
            ->assertHasErrors(['name' => 'required', 'phone' => 'required'])
            ->set('name', 'Ana Souza')
            ->set('phone', '(11) 1234-5678')
            ->call('confirm')
            ->assertHasErrors(['phone'])
            ->set('phone', '(11) 98888-7777')
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertSet('step', 6);

        $booking = Booking::sole();

        expect($booking->tenant_id)->toBe($this->tenant->id)
            ->and($booking->customer->name)->toBe('Ana Souza')
            ->and($booking->customer->phone)->toBe('11988887777')
            ->and($booking->customer->user_id)->toBeNull();
    });

    test('a visitor whose mobile already has a customer record books on it', function () {
        app(CurrentTenant::class)->set($this->tenant);
        $this->customer->update(['phone' => '11988887777']);
        app(CurrentTenant::class)->set(null);

        publicBooking()
            ->call('selectBarbershop', $this->tenant->id)
            ->call('selectService', $this->service->id)
            ->call('selectDate', '2026-11-02')
            ->call('selectProfessional', $this->professional->id)
            ->call('selectTime', '09:30')
            ->set('name', 'Outro Nome')
            ->set('phone', '(11) 98888-7777')
            ->call('confirm')
            ->assertSet('step', 6);

        expect(Booking::sole()->customer_id)->toBe($this->customer->id)
            ->and(Customer::count())->toBe(1);
    });

    test('only shows active barbershops and their own services', function () {
        $closed = Tenant::factory()->create(['name' => 'Barbearia Fechada', 'is_active' => false]);
        $other = Tenant::factory()->create(['name' => 'Outra Barbearia']);
        $foreign = inTenant($other, function () {
            $service = Service::factory()->create(['name' => 'Serviço de Outra']);
            bookableProfessional($service);

            return $service;
        });

        $component = publicBooking()
            ->assertSee('Outra Barbearia')
            ->assertDontSee('Barbearia Fechada')
            ->call('selectBarbershop', $closed->id)
            ->assertSet('step', 0)
            ->call('selectBarbershop', $this->tenant->id)
            ->assertSee('R$ 45,00')
            ->assertDontSee('Serviço de Outra')
            ->call('selectService', $foreign->id)
            ->assertSet('step', 1);

        $component->call('goBack')
            ->call('selectBarbershop', $other->id)
            ->assertSee('Serviço de Outra')
            ->assertDontSee('R$ 45,00');
    });

    test('a barbershop deactivated during the booking sends the visitor back to the first step', function () {
        $component = publicBooking()
            ->call('selectBarbershop', $this->tenant->id)
            ->call('selectService', $this->service->id);

        $this->tenant->update(['is_active' => false]);

        $component->call('$refresh')
            ->assertSet('step', 0)
            ->assertSet('tenantId', null);
    });
});

describe('barbershop in the url', function () {
    test('a visitor with the barbershop in the url skips the barbershop step', function () {
        $this->post(route('logout'));
        app(CurrentTenant::class)->set(null);

        Livewire::withQueryParams(['barbershop' => $this->tenant->slug])
            ->test('pages::bookings.booking-create')
            ->assertSet('step', 1)
            ->assertSet('barbershopFixed', true)
            ->assertDontSee('Escolha a barbearia')
            ->call('goBack')
            ->assertSet('step', 1);
    });

    test('an unknown barbershop in the url shows the barbershop step', function () {
        $this->post(route('logout'));
        app(CurrentTenant::class)->set(null);

        Livewire::withQueryParams(['barbershop' => 'nao-existe'])
            ->test('pages::bookings.booking-create')
            ->assertSet('step', 0)
            ->assertSee('Escolha a barbearia');
    });

    test('a logged customer without the barbershop in the url chooses one that accepts customers', function () {
        $withoutCustomers = Tenant::factory()->create(['name' => 'Só Equipe']);
        $withoutCustomers->roles()->detach(Role::findByName('customer'));
        app(CurrentTenant::class)->set(null);

        Livewire::test('pages::bookings.booking-create')
            ->assertSet('step', 0)
            ->assertSee($this->tenant->name)
            ->assertDontSee('Só Equipe');
    });
});

describe('customer details on the confirmation', function () {
    test('a customer with a record in the barbershop sees the connected card and books on it', function () {
        bookingAtTimeStep($this->service->id, $this->professional->id)
            ->call('selectTime', '09:30')
            ->assertSee('Conectado')
            ->assertSee('João Silva')
            ->assertDontSee('Nome completo')
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertSet('step', 6);

        expect(Booking::sole()->customer_id)->toBe($this->customer->id);
    });

    test('a customer without a record in another barbershop fills name and mobile and gets a record there', function () {
        $other = Tenant::factory()->create(['name' => 'Outra Barbearia']);
        [$service, $professional] = inTenant($other, function () {
            $service = Service::factory()->create();

            return [$service, bookableProfessional($service)];
        });
        app(CurrentTenant::class)->set(null);

        Livewire::withQueryParams(['barbershop' => $other->slug])
            ->test('pages::bookings.booking-create')
            ->call('selectService', $service->id)
            ->call('selectDate', '2026-11-02')
            ->call('selectProfessional', $professional->id)
            ->call('selectTime', '09:30')
            ->assertDontSee('Conectado')
            ->assertSee('Nome completo')
            ->assertDontSee('Já tem uma conta?')
            ->call('confirm')
            ->assertHasErrors(['name' => 'required', 'phone' => 'required'])
            ->set('name', 'João na Outra')
            ->set('phone', '(11) 97777-6666')
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertSet('step', 6);

        $record = $this->customer->user->customers()->where('tenant_id', $other->id)->sole();

        expect($record->name)->toBe('João na Outra')
            ->and($record->phone)->toBe('11977776666')
            ->and(Booking::withoutGlobalScopes()->sole()->customer_id)->toBe($record->id)
            ->and($this->customer->fresh()->name)->toBe('João Silva');
    });

    test('a mobile of a record linked to another account is refused', function () {
        $other = Tenant::factory()->create();
        [$service, $professional] = inTenant($other, function () {
            Customer::factory()->create(['phone' => '11977776666']);
            $service = Service::factory()->create();

            return [$service, bookableProfessional($service)];
        });
        app(CurrentTenant::class)->set(null);

        Livewire::withQueryParams(['barbershop' => $other->slug])
            ->test('pages::bookings.booking-create')
            ->call('selectService', $service->id)
            ->call('selectDate', '2026-11-02')
            ->call('selectProfessional', $professional->id)
            ->call('selectTime', '09:30')
            ->set('name', 'João')
            ->set('phone', '(11) 97777-6666')
            ->call('confirm')
            ->assertHasErrors(['phone'])
            ->assertSet('step', 5);

        expect(Booking::withoutGlobalScopes()->count())->toBe(0);
    });
});

describe('sign in from the confirmation', function () {
    beforeEach(function () {
        $this->post(route('logout'));
        app(CurrentTenant::class)->set(null);
    });

    test('the sign in link carries the choices and the customer returns to the confirmation', function () {
        $signInUrl = Livewire::withQueryParams(['barbershop' => $this->tenant->slug])
            ->test('pages::bookings.booking-create')
            ->call('selectService', $this->service->id)
            ->call('selectDate', '2026-11-02')
            ->call('selectProfessional', $this->professional->id)
            ->call('selectTime', '09:30')
            ->assertSee('Já tem uma conta?')
            ->get('signInUrl');

        parse_str((string) parse_url($signInUrl, PHP_URL_QUERY), $query);

        $this->get($signInUrl)->assertOk();

        expect(session('url.intended'))->toBe(route('public-bookings.create', $query));

        $this->actingAs($this->customer->user);

        Livewire::withQueryParams($query)
            ->test('pages::bookings.booking-create')
            ->assertSet('step', 5)
            ->assertSet('barbershopFixed', true)
            ->assertSet('professionalId', $this->professional->id)
            ->assertSet('time', '09:30')
            ->assertSee('Conectado');
    });

    test('a tampered state starts the booking over', function () {
        $this->actingAs($this->customer->user);

        Livewire::withQueryParams(['barbershop' => $this->tenant->slug, 'booking' => 'adulterado'])
            ->test('pages::bookings.booking-create')
            ->assertSet('step', 1)
            ->assertSet('serviceId', null)
            ->assertHasNoErrors();
    });
});

test('the header shows only after a barbershop is chosen', function () {
    $this->post(route('logout'));
    app(CurrentTenant::class)->set(null);

    $this->get(route('public-bookings.create'))
        ->assertOk()
        ->assertDontSee('data-flux-header', false);

    $this->get(route('public-bookings.create', ['barbershop' => $this->tenant->slug]))
        ->assertOk()
        ->assertSee('data-flux-header', false);

    Livewire::test('pages::bookings.booking-create')
        ->assertDontSee('data-flux-header', false)
        ->call('selectBarbershop', $this->tenant->id)
        ->assertSee('data-flux-header', false);
});

describe('account from the confirmation', function () {
    beforeEach(function () {
        $this->post(route('logout'));
        app(CurrentTenant::class)->set(null);
    });

    function signInUrlAtConfirmation(): string
    {
        return Livewire::test('pages::bookings.booking-create')
            ->call('selectBarbershop', test()->tenant->id)
            ->call('selectService', test()->service->id)
            ->call('selectDate', '2026-11-02')
            ->call('selectProfessional', test()->professional->id)
            ->call('selectTime', '09:30')
            ->assertSet('step', 5)
            ->get('signInUrl');
    }

    /** @return array<string, string> */
    function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    test('signing in with an existing account returns to the confirmation and books', function () {
        $query = queryOf(signInUrlAtConfirmation());

        $this->get(route('login', $query))->assertOk();

        Livewire::test('pages::auth.auth-login')
            ->set('email', $this->customer->user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('public-bookings.create', $query));

        $this->get(route('public-bookings.create', $query))->assertRedirect(route('bookings.create', $query));

        Livewire::withQueryParams($query)
            ->test('pages::bookings.booking-create')
            ->assertSet('step', 5)
            ->assertSee('Conectado')
            ->call('confirm')
            ->assertSet('step', 6);

        expect(Booking::withoutGlobalScopes()->sole()->customer_id)->toBe($this->customer->id);
    });

    test('creating an account from the login creates the user, the record and the booking', function () {
        $query = queryOf(signInUrlAtConfirmation());

        $this->get(route('login', $query))
            ->assertOk()
            ->assertSee(e(route('register', $query)), false);

        Livewire::withQueryParams($query)
            ->test('pages::auth.auth-register')
            ->assertSee(e(route('login', $query)), false)
            ->set('name', 'Ana Souza')
            ->set('email', 'ana@example.com')
            ->set('phone', '(11) 98888-7777')
            ->set('password', 'password123')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('bookings.create', $query));

        $user = User::withoutGlobalScopes()->where('email', 'ana@example.com')->sole();
        $record = $user->customers()->sole();

        $this->actingAs($user);

        Livewire::withQueryParams($query)
            ->test('pages::bookings.booking-create')
            ->assertSet('step', 5)
            ->assertSee('Conectado')
            ->assertSee('Ana Souza')
            ->call('confirm')
            ->assertSet('step', 6);

        expect($user->tenant_id)->toBeNull()
            ->and($record->tenant_id)->toBe($this->tenant->id)
            ->and($record->phone)->toBe('11988887777')
            ->and(Booking::withoutGlobalScopes()->sole()->customer_id)->toBe($record->id);
    });
});
