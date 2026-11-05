<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(CurrentTenant::class)->id() ?? Tenant::factory(),
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['tenant_id' => $attributes['tenant_id']]),
            'professional_id' => fn (array $attributes) => Professional::factory()->create(['tenant_id' => $attributes['tenant_id']]),
            'service_id' => fn (array $attributes) => Service::factory()->create(['tenant_id' => $attributes['tenant_id']]),
            'starts_at' => CarbonImmutable::tomorrow()->setTime(10, 0),
            'ends_at' => fn (array $attributes) => CarbonImmutable::parse($attributes['starts_at'])
                ->addMinutes($attributes['duration_minutes']),
            'price_cents' => 4500,
            'duration_minutes' => 30,
            'status' => BookingStatus::Scheduled,
            'notes' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Completed,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Cancelled,
        ]);
    }

    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::NoShow,
        ]);
    }
}
