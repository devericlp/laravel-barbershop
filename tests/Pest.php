<?php

use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->seed(RolePermissionSeeder::class);
        $this->tenant = Tenant::factory()->create();
        app(CurrentTenant::class)->set($this->tenant);
    })
    ->in('Feature');

/** @param list<array{0: string, 1: string}> $intervals */
function bookableProfessional(Service $service, array $intervals = [['09:00', '12:00']], int $dayOfWeek = 1): Professional
{
    $professional = Professional::factory()->create();
    $professional->services()->attach($service);

    foreach ($intervals as [$startsAt, $endsAt]) {
        $professional->workingHours()->create([
            'day_of_week' => $dayOfWeek,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    return $professional;
}

function inTenant(Tenant $tenant, Closure $callback): mixed
{
    $current = app(CurrentTenant::class);
    $previous = $current->get();

    $current->set($tenant);

    try {
        return $callback();
    } finally {
        $current->set($previous);
    }
}
