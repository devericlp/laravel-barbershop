<?php

use App\Actions\Professionals\DeleteProfessional;
use App\Exceptions\CannotDeleteRecordException;
use App\Models\Booking;
use App\Models\Professional;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('deletes a professional without bookings and its photo', function () {
    Storage::fake('public');
    $professional = Professional::factory()->create([
        'photo_path' => UploadedFile::fake()->image('photo.jpg')->store('professionals', 'public'),
    ]);
    $photo = $professional->photo_path;

    app(DeleteProfessional::class)->handle($professional);

    expect(Professional::count())->toBe(0);
    Storage::disk('public')->assertMissing($photo);
});

test('refuses to delete a professional with bookings', function () {
    $professional = Professional::factory()->create();
    Booking::factory()->for($professional)->create();

    app(DeleteProfessional::class)->handle($professional);
})->throws(CannotDeleteRecordException::class);
