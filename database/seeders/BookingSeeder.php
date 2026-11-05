<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::all();
        $professionals = Professional::with(['services', 'workingHours'])->get();
        $today = CarbonImmutable::today();

        foreach (range(-30, 14) as $offset) {
            $date = $today->addDays($offset);

            foreach ($professionals as $professional) {
                $intervals = $professional->workingHours->where('day_of_week', $date->dayOfWeek);

                foreach ($intervals as $interval) {
                    $this->fillInterval($date, $interval, $professional, $customers);
                }
            }
        }
    }

    /** @param Collection<int, Customer> $customers */
    private function fillInterval(CarbonImmutable $date, WorkingHour $interval, Professional $professional, Collection $customers): void
    {
        $cursor = $date->setTimeFromTimeString($interval->starts_at);
        $end = $date->setTimeFromTimeString($interval->ends_at);
        $services = $professional->services->where('is_active', true);

        while ($cursor < $end) {
            if (fake()->boolean(55)) {
                $cursor = $cursor->addMinutes(15);

                continue;
            }

            /** @var Service $service */
            $service = $services->random();
            $endsAt = $cursor->addMinutes($service->duration_minutes);

            if ($endsAt > $end) {
                break;
            }

            Booking::factory()->create([
                'customer_id' => $customers->random()->id,
                'professional_id' => $professional->id,
                'service_id' => $service->id,
                'starts_at' => $cursor,
                'ends_at' => $endsAt,
                'price_cents' => $service->price_cents,
                'duration_minutes' => $service->duration_minutes,
                'status' => $this->statusFor($cursor),
            ]);

            $cursor = $endsAt;
        }
    }

    private function statusFor(CarbonImmutable $startsAt): BookingStatus
    {
        if ($startsAt->isFuture()) {
            return fake()->randomElement([
                ...array_fill(0, 9, BookingStatus::Scheduled),
                BookingStatus::Cancelled,
            ]);
        }

        return fake()->randomElement([
            ...array_fill(0, 8, BookingStatus::Completed),
            BookingStatus::Cancelled,
            BookingStatus::NoShow,
        ]);
    }
}
