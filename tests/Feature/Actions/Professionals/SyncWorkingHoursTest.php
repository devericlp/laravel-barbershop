<?php

use App\Actions\Professionals\SyncWorkingHours;
use App\Models\Professional;
use Illuminate\Validation\ValidationException;

test('replaces the working hours with the given intervals', function () {
    $professional = Professional::factory()->create();
    $professional->workingHours()->create(['day_of_week' => 2, 'starts_at' => '08:00', 'ends_at' => '10:00']);

    app(SyncWorkingHours::class)->handle($professional, [
        ['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00'],
        ['day_of_week' => 1, 'starts_at' => '13:00', 'ends_at' => '18:00'],
    ]);

    expect($professional->workingHours()->orderBy('starts_at')->get(['day_of_week', 'starts_at', 'ends_at'])->toArray())->toBe([
        ['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00'],
        ['day_of_week' => 1, 'starts_at' => '13:00', 'ends_at' => '18:00'],
    ]);
});

test('rejects an interval that ends before it starts', function () {
    app(SyncWorkingHours::class)->handle(Professional::factory()->create(), [
        ['day_of_week' => 1, 'starts_at' => '12:00', 'ends_at' => '09:00'],
    ]);
})->throws(ValidationException::class);

test('rejects overlapping intervals on the same day', function () {
    app(SyncWorkingHours::class)->handle(Professional::factory()->create(), [
        ['day_of_week' => 1, 'starts_at' => '13:00', 'ends_at' => '18:00'],
        ['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '13:30'],
    ]);
})->throws(ValidationException::class, 'Os intervalos de Segunda se sobrepõem.');

test('accepts adjacent intervals and the same hours on different days', function () {
    $professional = Professional::factory()->create();

    app(SyncWorkingHours::class)->handle($professional, [
        ['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00'],
        ['day_of_week' => 1, 'starts_at' => '12:00', 'ends_at' => '18:00'],
        ['day_of_week' => 2, 'starts_at' => '09:00', 'ends_at' => '12:00'],
    ]);

    expect($professional->workingHours()->count())->toBe(3);
});
