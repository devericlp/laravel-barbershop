<?php

use App\Actions\Services\CreateService;
use App\Data\ServiceData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('creates a service', function () {
    $service = app(CreateService::class)->handle(new ServiceData('Corte', 4500, 30));

    expect($service->only(['name', 'description', 'image_path', 'price_cents', 'duration_minutes', 'is_active']))->toBe([
        'name' => 'Corte',
        'description' => null,
        'image_path' => null,
        'price_cents' => 4500,
        'duration_minutes' => 30,
        'is_active' => true,
    ]);
});

test('creates a service with description and image', function () {
    Storage::fake('public');

    $service = app(CreateService::class)->handle(new ServiceData(
        name: 'Barba',
        priceCents: 3500,
        durationMinutes: 30,
        description: 'Barba com toalha quente.',
        image: UploadedFile::fake()->image('barba.jpg'),
    ));

    expect($service->description)->toBe('Barba com toalha quente.')
        ->and($service->image_path)->toStartWith('services/');

    Storage::disk('public')->assertExists($service->image_path);
});
