<?php

use App\Actions\Services\UpdateService;
use App\Data\ServiceData;
use App\Models\Booking;
use App\Models\Service;

test('updates a service without changing the copied values of its bookings', function () {
    $service = Service::factory()->create(['price_cents' => 4500]);
    $booking = Booking::factory()->for($service)->create(['price_cents' => 4500]);

    app(UpdateService::class)->handle($service, new ServiceData('Corte degradê', 5500, 45, 'Degradê na máquina.'));

    expect($service->fresh()->only(['name', 'description', 'price_cents', 'duration_minutes']))->toBe([
        'name' => 'Corte degradê',
        'description' => 'Degradê na máquina.',
        'price_cents' => 5500,
        'duration_minutes' => 45,
    ])->and($booking->fresh()->price_cents)->toBe(4500);
});

test('keeps the status when updating', function () {
    $service = Service::factory()->inactive()->create();

    app(UpdateService::class)->handle($service, new ServiceData('Pigmentação', 5000, 45));

    expect($service->fresh()->is_active)->toBeFalse();
});
