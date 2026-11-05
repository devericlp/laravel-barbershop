<?php

use App\Actions\Professionals\CreateProfessional;
use App\Data\ProfessionalData;
use App\Enums\UserRole;
use App\Models\Service;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('creates a professional with services, working hours and photo', function () {
    Storage::fake('public');
    $services = Service::factory()->count(2)->create();

    $professional = app(CreateProfessional::class)->handle(new ProfessionalData(
        name: 'Rafael Mendes',
        email: 'rafael@email.com',
        serviceIds: $services->pluck('id')->all(),
        workingHours: [['day_of_week' => 2, 'starts_at' => '09:00', 'ends_at' => '18:00']],
        photo: UploadedFile::fake()->image('rafael.jpg'),
    ), 'secret-password');

    expect($professional->name)->toBe('Rafael Mendes')
        ->and($professional->user->userRoles())->toBe([UserRole::Professional])
        ->and(Hash::check('secret-password', $professional->user->password))->toBeTrue()
        ->and($professional->services()->count())->toBe(2)
        ->and($professional->workingHours()->count())->toBe(1)
        ->and($professional->photo_path)->not->toBeNull();

    Storage::disk('public')->assertExists($professional->photo_path);
});

test('creates an active professional without services or working hours', function () {
    $professional = app(CreateProfessional::class)->handle(new ProfessionalData(
        name: 'Thiago Rocha',
        email: 'thiago@email.com',
    ), 'secret-password');

    expect($professional->is_active)->toBeTrue()
        ->and($professional->services()->count())->toBe(0)
        ->and($professional->workingHours()->count())->toBe(0);
});
