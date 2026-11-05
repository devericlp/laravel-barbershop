<?php

use App\Models\Booking;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

test('guests are redirected to login', function () {
    auth()->logout();

    $this->get(route('services.index'))->assertRedirect(route('login'));
});

test('lists services with price and duration', function () {
    Service::factory()->create(['name' => 'Corte + barba', 'price_cents' => 7000, 'duration_minutes' => 60]);

    $this->get(route('services.index'))
        ->assertOk()
        ->assertSee('Corte + barba')
        ->assertSee('R$ 70,00')
        ->assertSee('60 min');
});

test('searches services by name', function () {
    Service::factory()->create(['name' => 'Corte infantil']);
    Service::factory()->create(['name' => 'Barba']);

    $component = Livewire::test('pages::services.service-index')->set('searchInput', 'infantil')->call('applySearch');

    expect($component->get('services')->pluck('name')->all())->toBe(['Corte infantil'])
        ->and($component->get('activeFilters'))->toBe([['key' => 'search', 'label' => 'Pesquisa: infantil']]);
});

test('filters services by status and duration once applied', function () {
    Service::factory()->create(['name' => 'Barba', 'duration_minutes' => 30, 'is_active' => true]);
    Service::factory()->create(['name' => 'Corte + barba', 'duration_minutes' => 60, 'is_active' => true]);
    Service::factory()->create(['name' => 'Pigmentação', 'duration_minutes' => 90, 'is_active' => false]);

    $component = Livewire::test('pages::services.service-index')
        ->set('filters.status', 'active')
        ->set('filters.duration', 'medium');

    expect($component->get('services')->total())->toBe(3);

    $component->call('applyFilters')->assertHasNoErrors();

    expect($component->get('services')->pluck('name')->all())->toBe(['Corte + barba'])
        ->and($component->get('activeFilters'))->toBe([
            ['key' => 'status', 'label' => 'Status: Ativo'],
            ['key' => 'duration', 'label' => 'Duração: 31 a 60 min'],
        ]);

    $component->set('filters.duration', 'long')->set('filters.status', 'inactive')->call('applyFilters');

    expect($component->get('services')->pluck('name')->all())->toBe(['Pigmentação']);
});

test('shows the empty state with the filters still available', function () {
    Livewire::test('pages::services.service-index')
        ->set('searchInput', 'Inexistente')->call('applySearch')
        ->assertSee('Nenhum serviço encontrado')
        ->assertSee('Limpar filtros')
        ->assertSee('Pesquisar serviços...');
});

test('paginates services without one query per row', function () {
    $queriesFor = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test('pages::services.service-index');
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    Service::factory()->create();
    $queriesFor();
    $fewRows = $queriesFor();
    Service::factory()->count(11)->create();

    expect($queriesFor())->toBe($fewRows)
        ->and(Livewire::test('pages::services.service-index')->get('services')->count())->toBe(10);
});

test('creates a service converting the price to cents', function () {
    Livewire::test('pages::services.service-index')
        ->call('create')
        ->set('name', 'Barba')
        ->set('price', '1.035,50')
        ->set('durationMinutes', 30)
        ->call('save')
        ->assertHasNoErrors();

    expect(Service::sole()->only(['name', 'price_cents', 'duration_minutes', 'is_active']))->toBe([
        'name' => 'Barba',
        'price_cents' => 103550,
        'duration_minutes' => 30,
        'is_active' => true,
    ]);
});

test('saves the description and image and shows them in the list', function () {
    Storage::fake('public');

    Livewire::test('pages::services.service-index')
        ->call('create')
        ->set('name', 'Barba')
        ->set('description', 'Barba com toalha quente.')
        ->set('image', UploadedFile::fake()->image('barba.jpg'))
        ->set('price', '35,00')
        ->set('durationMinutes', 30)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Barba com toalha quente.');

    $service = Service::sole();

    expect($service->description)->toBe('Barba com toalha quente.');
    Storage::disk('public')->assertExists($service->image_path);

    Livewire::test('pages::services.service-index')
        ->call('edit', $service->id)
        ->assertSet('description', 'Barba com toalha quente.')
        ->call('clearImage')
        ->set('description', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($service->fresh()->only(['description', 'image_path']))->toBe(['description' => null, 'image_path' => null]);
});

test('rejects files that are not images', function () {
    Livewire::test('pages::services.service-index')
        ->set('image', UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'))
        ->assertHasErrors(['image']);
});

test('validates price and duration', function () {
    Livewire::test('pages::services.service-index')
        ->set('name', 'Barba')
        ->set('price', '0,00')
        ->set('durationMinutes', 0)
        ->call('save')
        ->assertHasErrors(['durationMinutes']);

    Livewire::test('pages::services.service-index')
        ->set('name', 'Barba')
        ->set('price', '0,00')
        ->set('durationMinutes', 30)
        ->call('save')
        ->assertHasErrors(['price']);

    expect(Service::count())->toBe(0);
});

test('edits a service', function () {
    $service = Service::factory()->create(['price_cents' => 4500]);

    Livewire::test('pages::services.service-index')
        ->call('edit', $service->id)
        ->assertSet('price', '45,00')
        ->set('price', '50,00')
        ->call('save')
        ->assertHasNoErrors();

    expect($service->fresh()->price_cents)->toBe(5000);
});

test('deactivates and activates a service after confirming', function () {
    $service = Service::factory()->create(['name' => 'Barba']);

    $component = Livewire::test('pages::services.service-index')
        ->call('confirmStatusChange', $service->id)
        ->assertSee('Inativar serviço');

    expect($service->fresh()->is_active)->toBeTrue();

    $component->call('changeStatus');
    expect($service->fresh()->is_active)->toBeFalse();

    $component->call('confirmStatusChange', $service->id)
        ->assertSee('Ativar serviço')
        ->call('changeStatus');
    expect($service->fresh()->is_active)->toBeTrue();
});

test('requires permission to change the status', function () {
    Role::findByName('manager')->revokePermissionTo('services.toggle-status');
    $service = Service::factory()->create();

    Livewire::test('pages::services.service-index')
        ->assertDontSee('Inativar')
        ->call('confirmStatusChange', $service->id)
        ->assertForbidden();

    expect($service->fresh()->is_active)->toBeTrue();
});

test('refuses to delete a service with bookings', function () {
    $service = Service::factory()->create();
    Booking::factory()->for($service)->create();

    Livewire::test('pages::services.service-index')
        ->call('confirmDelete', $service->id)
        ->call('delete');

    expect(Service::count())->toBe(1);
});
