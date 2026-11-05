<?php

use App\Actions\Services\UpdateServiceStatus;
use App\Models\Booking;
use App\Models\Service;

test('deactivates and activates a service', function () {
    $service = Service::factory()->create();

    app(UpdateServiceStatus::class)->handle($service, false);
    expect($service->fresh()->is_active)->toBeFalse();

    app(UpdateServiceStatus::class)->handle($service, true);
    expect($service->fresh()->is_active)->toBeTrue();
});

test('deactivates a service that has bookings', function () {
    $service = Service::factory()->create();
    Booking::factory()->for($service)->create();

    app(UpdateServiceStatus::class)->handle($service, false);

    expect($service->fresh()->is_active)->toBeFalse()
        ->and($service->bookings()->count())->toBe(1);
});
