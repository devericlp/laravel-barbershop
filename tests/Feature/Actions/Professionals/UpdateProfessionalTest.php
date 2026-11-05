<?php

use App\Actions\Professionals\UpdateProfessional;
use App\Data\ProfessionalData;
use App\Models\Professional;
use App\Models\Service;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->professional = Professional::factory()->create([
        'photo_path' => UploadedFile::fake()->image('old.jpg')->store('professionals', 'public'),
    ]);
    $this->professional->services()->attach(Service::factory()->create());
});

test('updates data, services and working hours keeping the photo', function () {
    $service = Service::factory()->create();
    $oldPhoto = $this->professional->photo_path;

    app(UpdateProfessional::class)->handle($this->professional, new ProfessionalData(
        name: 'Lucas Andrade',
        email: 'lucas@email.com',
        serviceIds: [$service->id],
        workingHours: [['day_of_week' => 3, 'starts_at' => '10:00', 'ends_at' => '14:00']],
    ));

    $professional = $this->professional->fresh();

    expect($professional->name)->toBe('Lucas Andrade')
        ->and($professional->user->email)->toBe('lucas@email.com')
        ->and($professional->is_active)->toBeTrue()
        ->and($professional->services->pluck('id')->all())->toBe([$service->id])
        ->and($professional->workingHours->first()->day_of_week)->toBe(3)
        ->and($professional->photo_path)->toBe($oldPhoto);
});

test('replaces the photo deleting the previous file', function () {
    $oldPhoto = $this->professional->photo_path;

    app(UpdateProfessional::class)->handle($this->professional, new ProfessionalData(
        name: 'Lucas',
        email: 'lucas@email.com',
        serviceIds: [],
        workingHours: [],
        photo: UploadedFile::fake()->image('new.png'),
    ));

    Storage::disk('public')->assertMissing($oldPhoto);
    Storage::disk('public')->assertExists($this->professional->fresh()->photo_path);
});

test('removes the photo', function () {
    $oldPhoto = $this->professional->photo_path;

    app(UpdateProfessional::class)->handle($this->professional, new ProfessionalData(
        name: 'Lucas',
        email: 'lucas@email.com',
        serviceIds: [],
        workingHours: [],
        removePhoto: true,
    ));

    Storage::disk('public')->assertMissing($oldPhoto);
    expect($this->professional->fresh()->photo_path)->toBeNull();
});

test('keeps services and working hours when they are not sent', function () {
    $serviceIds = $this->professional->services->pluck('id')->all();
    $this->professional->workingHours()->create(['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '18:00']);

    app(UpdateProfessional::class)->handle($this->professional, new ProfessionalData(
        name: 'Lucas',
        email: 'lucas@email.com',
    ));

    $professional = $this->professional->fresh();

    expect($professional->services->pluck('id')->all())->toBe($serviceIds)
        ->and($professional->workingHours()->count())->toBe(1);
});

test('clears services and working hours when empty lists are sent', function () {
    $this->professional->workingHours()->create(['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '18:00']);

    app(UpdateProfessional::class)->handle($this->professional, new ProfessionalData(
        name: 'Lucas',
        email: 'lucas@email.com',
        serviceIds: [],
        workingHours: [],
    ));

    $professional = $this->professional->fresh();

    expect($professional->services)->toBeEmpty()
        ->and($professional->workingHours()->count())->toBe(0);
});
