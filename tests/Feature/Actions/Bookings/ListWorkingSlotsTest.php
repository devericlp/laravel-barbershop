<?php

use App\Actions\Bookings\ListWorkingSlots;
use App\Models\Booking;
use App\Models\Service;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 07:00'));
    $this->service = Service::factory()->create(['duration_minutes' => 30]);
});

function workingSlots(mixed $professional, int $duration, string $date): array
{
    return array_map(
        fn (CarbonImmutable $slot) => $slot->format('H:i'),
        app(ListWorkingSlots::class)->handle($professional, $duration, CarbonImmutable::parse($date)),
    );
}

test('lists every start that fits inside the working intervals, including booked ones', function () {
    $professional = bookableProfessional($this->service, [['09:00', '10:00'], ['13:00', '13:45']]);
    Booking::factory()->for($professional)->create(['starts_at' => CarbonImmutable::parse('2026-11-02 09:30')]);

    expect(workingSlots($professional, 30, '2026-11-02'))->toBe(['09:00', '09:15', '09:30', '13:00', '13:15']);
});

test('returns nothing on a day without working hours', function () {
    $professional = bookableProfessional($this->service);

    expect(workingSlots($professional, 30, '2026-11-03'))->toBe([]);
});
