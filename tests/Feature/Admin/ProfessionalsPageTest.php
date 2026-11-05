<?php

use App\Models\Professional;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->manager()->create()));

test('lists professionals with services and working days', function () {
    $professional = bookableProfessional(Service::factory()->create(['name' => 'Corte']));
    $professional->user->update(['name' => 'Rafael Mendes']);

    $this->get(route('professionals.index'))
        ->assertOk()
        ->assertSee('Rafael Mendes')
        ->assertSee('Corte')
        ->assertSee('Seg');
});

test('searches professionals by name or email and filters by status and service', function () {
    $corte = Service::factory()->create(['name' => 'Corte']);
    $barba = Service::factory()->create(['name' => 'Barba']);
    Professional::factory()->named('Rafael Mendes')->create()->services()->attach($corte);
    Professional::factory()->named('Lucas Andrade')->create(['is_active' => false])->services()->attach($barba);

    $component = Livewire::test('pages::professionals.professional-index')
        ->set('searchInput', 'rafael')
        ->call('applySearch');

    expect($component->get('professionals')->getCollection()->map->name->all())->toBe(['Rafael Mendes']);

    $component->call('clearFilters')
        ->set('filters.service', (string) $barba->id)
        ->set('filters.status', 'inactive')
        ->call('applyFilters')
        ->assertSee('Serviço: Barba')
        ->assertSee('Status: Inativo');

    expect($component->get('professionals')->getCollection()->map->name->all())->toBe(['Lucas Andrade']);
});

test('filters professionals that work on any of the selected days', function () {
    $service = Service::factory()->create();
    $monday = bookableProfessional($service, [['09:00', '18:00']], 1);
    $monday->user->update(['name' => 'Segunda Feira']);
    $saturday = bookableProfessional($service, [['09:00', '13:00']], 6);
    $saturday->user->update(['name' => 'Sabado Manha']);
    bookableProfessional($service, [['09:00', '18:00']], 3)->user->update(['name' => 'Quarta Feira']);

    $component = Livewire::test('pages::professionals.professional-index')
        ->set('filters.days', ['1', '6'])
        ->call('applyFilters')
        ->assertHasNoErrors()
        ->assertSee('Dias: Segunda')
        ->assertSee('Dias: Sábado');

    expect($component->get('professionals')->getCollection()->map->name->sort()->values()->all())->toBe(['Sabado Manha', 'Segunda Feira']);

    $component->call('removeFilter', 'days:1');

    expect($component->get('professionals')->getCollection()->map->name->all())->toBe(['Sabado Manha']);
});

test('lists professionals without one query per row', function () {
    $queriesFor = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test('pages::professionals.professional-index');
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    bookableProfessional(Service::factory()->create());
    $queriesFor();
    $fewRows = $queriesFor();
    bookableProfessional(Service::factory()->create());
    bookableProfessional(Service::factory()->create());

    expect($queriesFor())->toBe($fewRows);
});

test('opens the wizard empty for a new professional on the details step', function () {
    Livewire::test('professional-form')
        ->call('open')
        ->assertSet('step', 1)
        ->assertSet('professionalId', null)
        ->assertSet('workingHours', array_fill(0, 7, []))
        ->assertSee('Novo profissional');
});

test('creates a professional going through every step', function () {
    Storage::fake('public');
    $service = Service::factory()->create();

    $component = Livewire::test('professional-form')
        ->call('open')
        ->set('name', 'Thiago Rocha')
        ->set('email', 'thiago@email.com')
        ->set('password', 'password')
        ->set('photo', UploadedFile::fake()->image('thiago.jpg'))
        ->call('continue')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->set('serviceIds', [(string) $service->id])
        ->call('continue')
        ->assertSet('step', 3)
        ->call('addInterval', 3)
        ->call('addInterval', 3)
        ->set('workingHours.3.0.starts_at', '10:00')
        ->set('workingHours.3.0.ends_at', '14:00')
        ->set('workingHours.3.1.starts_at', '15:00')
        ->set('workingHours.3.1.ends_at', '20:00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('professional-saved');

    $professional = Professional::sole();

    expect($professional->services->pluck('id')->all())->toBe([$service->id])
        ->and($professional->is_active)->toBeTrue()
        ->and($professional->user->email)->toBe('thiago@email.com')
        ->and($professional->user->tenant_id)->toBe($this->tenant->id)
        ->and($professional->workingHours()->count())->toBe(2)
        ->and($professional->photo_path)->not->toBeNull();
});

test('validates the details before moving on and keeps what was filled when going back', function () {
    Livewire::test('professional-form')
        ->call('open')
        ->set('name', 'Thiago Rocha')
        ->call('continue')
        ->assertHasErrors(['email', 'password'])
        ->assertSet('step', 1)
        ->set('email', 'thiago@email.com')
        ->set('password', 'password')
        ->call('continue')
        ->assertSet('step', 2)
        ->call('back')
        ->assertSet('step', 1)
        ->assertSet('name', 'Thiago Rocha');
});

test('continuing through the optional steps without filling them creates a professional without services and working hours', function () {
    Livewire::test('professional-form')
        ->call('open')
        ->set('name', 'Thiago Rocha')
        ->set('email', 'thiago@email.com')
        ->set('password', 'password')
        ->call('continue')
        ->call('continue')
        ->assertSet('step', 3)
        ->call('save')
        ->assertHasNoErrors();

    $professional = Professional::sole();

    expect($professional->services)->toBeEmpty()
        ->and($professional->workingHours()->count())->toBe(0);
});

test('validates working hour intervals before saving', function () {
    Livewire::test('professional-form')
        ->call('open')
        ->set('name', 'Thiago Rocha')
        ->set('email', 'thiago@email.com')
        ->set('password', 'password')
        ->call('continue')
        ->call('continue')
        ->call('addInterval', 1)
        ->set('workingHours.1.0.starts_at', '18:00')
        ->set('workingHours.1.0.ends_at', '09:00')
        ->call('save')
        ->assertHasErrors(['workingHours.1.0.ends_at'])
        ->set('workingHours.1', [
            ['starts_at' => '09:00', 'ends_at' => '13:00'],
            ['starts_at' => '12:00', 'ends_at' => '18:00'],
        ])
        ->call('save')
        ->assertHasErrors(['workingHours'])
        ->assertSet('step', 3);

    expect(Professional::count())->toBe(0);
});

test('returns to the details step when the server rejects them on save', function () {
    $component = Livewire::test('professional-form')
        ->call('open')
        ->set('name', 'Thiago Rocha')
        ->set('email', 'thiago@email.com')
        ->set('password', 'password')
        ->call('continue')
        ->call('continue');

    User::factory()->create(['email' => 'thiago@email.com']);

    $component->call('save')
        ->assertHasErrors(['email'])
        ->assertSet('step', 1)
        ->assertSet('name', 'Thiago Rocha');
});

test('loads an existing professional and keeps its services and working hours when continuing', function () {
    $service = Service::factory()->create();
    $professional = bookableProfessional($service, [['09:00', '12:00']], 2);

    Livewire::test('professional-form')
        ->call('open', $professional->id)
        ->assertSet('step', 1)
        ->assertSet('password', '')
        ->assertSet('workingHours.2.0.starts_at', '09:00')
        ->assertSet('serviceIds', [(string) $service->id])
        ->assertSee('Editar profissional')
        ->assertSee('Deixe em branco para manter a senha atual.')
        ->set('name', 'Novo nome')
        ->call('continue')
        ->call('continue')
        ->call('save')
        ->assertHasNoErrors();

    $professional = $professional->fresh();

    expect($professional->name)->toBe('Novo nome')
        ->and($professional->services->pluck('id')->all())->toBe([$service->id])
        ->and($professional->workingHours()->count())->toBe(1);
});

test('confirming emptied steps on edit removes services and working hours', function () {
    $service = Service::factory()->create();
    $professional = bookableProfessional($service, [['09:00', '12:00']], 2);

    Livewire::test('professional-form')
        ->call('open', $professional->id)
        ->call('continue')
        ->set('serviceIds', [])
        ->call('continue')
        ->call('removeInterval', 2, 0)
        ->call('save')
        ->assertHasNoErrors();

    expect($professional->fresh()->services)->toBeEmpty()
        ->and($professional->workingHours()->count())->toBe(0);
});

test('reports the status of each step', function () {
    $component = Livewire::test('professional-form')
        ->call('open')
        ->set('name', 'Thiago Rocha')
        ->set('email', 'thiago@email.com')
        ->set('password', 'password')
        ->call('continue');

    expect(collect($component->get('steps'))->pluck('status', 'number')->all())->toBe([1 => 'complete', 2 => 'current', 3 => 'pending']);

    $component->call('continue');

    expect(collect($component->get('steps'))->pluck('status', 'number')->all())->toBe([1 => 'complete', 2 => 'complete', 3 => 'current']);

    $component->call('back');

    expect(collect($component->get('steps'))->pluck('status', 'number')->all())->toBe([1 => 'complete', 2 => 'current', 3 => 'pending']);
});

test('deactivates and activates a professional after confirming', function () {
    $professional = Professional::factory()->named('Rafael Mendes')->create();

    $component = Livewire::test('pages::professionals.professional-index')
        ->call('confirmStatusChange', $professional->id)
        ->assertSee('Inativar profissional');

    expect($professional->fresh()->is_active)->toBeTrue();

    $component->call('changeStatus');
    expect($professional->fresh()->is_active)->toBeFalse();

    $component->call('confirmStatusChange', $professional->id)->call('changeStatus');
    expect($professional->fresh()->is_active)->toBeTrue();
});

test('requires permission to change the status of a professional', function () {
    Role::findByName('manager')->revokePermissionTo('professionals.toggle-status');
    $professional = Professional::factory()->create();

    Livewire::test('pages::professionals.professional-index')
        ->call('confirmStatusChange', $professional->id)
        ->assertForbidden();
});

test('the wizard opens an existing professional at the requested step', function () {
    $professional = Professional::factory()->create();

    Livewire::test('professional-form')
        ->call('open', $professional->id, 2)
        ->assertSet('step', 2)
        ->assertSet('confirmedSteps', [1]);
});
