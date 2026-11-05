<?php

use App\Actions\Services\DeleteService;
use App\Actions\Services\ReplaceServiceImage;
use App\Exceptions\CannotDeleteRecordException;
use App\Models\Booking;
use App\Models\Service;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('deletes a service without bookings', function () {
    $service = Service::factory()->create();

    app(DeleteService::class)->handle($service);

    expect(Service::count())->toBe(0);
});

test('deletes the image together with the service', function () {
    Storage::fake('public');
    $service = Service::factory()->create();
    app(ReplaceServiceImage::class)->handle($service, UploadedFile::fake()->image('corte.jpg'));
    $path = $service->image_path;

    app(DeleteService::class)->handle($service);

    Storage::disk('public')->assertMissing($path);
});

test('refuses to delete a service with bookings', function () {
    $service = Service::factory()->create();
    Booking::factory()->for($service)->cancelled()->create();

    app(DeleteService::class)->handle($service);
})->throws(CannotDeleteRecordException::class);
