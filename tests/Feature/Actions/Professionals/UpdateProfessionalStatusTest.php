<?php

use App\Actions\Professionals\UpdateProfessionalStatus;
use App\Enums\UserRole;
use App\Models\Professional;

test('deactivates and activates a professional', function () {
    $professional = Professional::factory()->create();

    app(UpdateProfessionalStatus::class)->handle($professional, false);
    expect($professional->fresh()->is_active)->toBeFalse();

    app(UpdateProfessionalStatus::class)->handle($professional, true);
    expect($professional->fresh()->is_active)->toBeTrue();
});

test('activating gives back the professional role removed with the profile', function () {
    $professional = Professional::factory()->create(['is_active' => false]);
    $professional->user->syncRoles([UserRole::Manager->value]);

    app(UpdateProfessionalStatus::class)->handle($professional, true);

    expect($professional->user->fresh()->userRoles())->toBe([UserRole::Manager, UserRole::Professional]);
});
