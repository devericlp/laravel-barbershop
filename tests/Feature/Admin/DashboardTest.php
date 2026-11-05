<?php

use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users see the dashboard with the sidebar', function () {
    $user = User::factory()->create(['name' => 'Ana Souza']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Agenda')
        ->assertSee('Serviços')
        ->assertSee('Profissionais')
        ->assertSee('Ana Souza')
        ->assertSee('Sair');
});

test('indicators reflect the bookings of the period', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00'));
    $this->actingAs(User::factory()->create());

    $haircut = Service::factory()->create(['name' => 'Corte']);
    $day = CarbonImmutable::parse('2026-11-05 10:00');

    Booking::factory()->for($haircut)->completed()->create(['starts_at' => $day, 'price_cents' => 4500]);
    Booking::factory()->for($haircut)->create(['starts_at' => CarbonImmutable::parse('2026-11-20 10:00'), 'price_cents' => 4500]);
    Booking::factory()->cancelled()->create(['starts_at' => $day, 'price_cents' => 7000]);
    Booking::factory()->noShow()->create(['starts_at' => $day, 'price_cents' => 7000]);
    Booking::factory()->completed()->create(['starts_at' => CarbonImmutable::parse('2026-10-20 10:00')]);

    $component = Livewire::test('pages::dashboard')
        ->assertSet('totals', [
            'bookings' => 4,
            'completed' => 1,
            'expectedValue' => 9000,
            'completedValue' => 4500,
            'cancelled' => 1,
            'noShow' => 1,
        ]);

    expect($component->get('topServices')[0])->toBe(['name' => 'Corte', 'total' => 2, 'percent' => 100])
        ->and(collect($component->get('chart'))->firstWhere('date', '2026-11-05')['bookings'])->toBe(3)
        ->and($component->get('upcoming'))->toHaveCount(1);

    $component->set('range', ['start' => '2026-10-01', 'end' => '2026-10-31'])
        ->assertSet('totals.bookings', 1);
});
