<?php

use App\Actions\Services\ReplaceServiceImage;
use App\Models\Service;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

test('replaces the image and deletes the previous file', function () {
    $service = Service::factory()->create();
    app(ReplaceServiceImage::class)->handle($service, UploadedFile::fake()->image('old.jpg'));
    $oldPath = $service->image_path;

    app(ReplaceServiceImage::class)->handle($service, UploadedFile::fake()->image('new.png'));

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($service->image_path);
});

test('keeps the image when nothing is sent', function () {
    $service = Service::factory()->create();
    app(ReplaceServiceImage::class)->handle($service, UploadedFile::fake()->image('corte.jpg'));
    $path = $service->image_path;

    app(ReplaceServiceImage::class)->handle($service, null);

    expect($service->fresh()->image_path)->toBe($path);
});

test('removes the image', function () {
    $service = Service::factory()->create();
    app(ReplaceServiceImage::class)->handle($service, UploadedFile::fake()->image('corte.jpg'));
    $path = $service->image_path;

    app(ReplaceServiceImage::class)->handle($service, null, removeImage: true);

    expect($service->fresh()->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});
