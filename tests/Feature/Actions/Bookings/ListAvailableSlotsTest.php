<?php

use App\Actions\Bookings\ListAvailableSlots;
use App\Enums\BookingChannel;
use App\Models\Booking;
use App\Models\Professional;
use App\Models\Service;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 07:00'));
    $this->monday = CarbonImmutable::parse('2026-11-02');
    $this->service = Service::factory()->create(['duration_minutes' => 30]);
});

function slots(Professional $professional, Service $service, CarbonImmutable $date, BookingChannel $channel = BookingChannel::Public, ?Booking $ignoring = null): array
{
    return app(ListAvailableSlots::class)->handle($professional, $service, $date, $channel, $ignoring);
}

test('generates starts every 15 minutes that fit inside each interval', function () {
    $professional = bookableProfessional($this->service, [['09:00', '10:00'], ['13:00', '13:45']]);

    expect(slots($professional, $this->service, $this->monday))
        ->toBe(['09:00', '09:15', '09:30', '13:00', '13:15']);
});

test('returns nothing on days without working hours', function () {
    $professional = bookableProfessional($this->service);

    expect(slots($professional, $this->service, $this->monday->addDay()))->toBe([]);
});

test('skips starts that overlap occupying bookings and frees cancelled ones', function () {
    $professional = bookableProfessional($this->service, [['09:00', '10:30']]);

    Booking::factory()->for($professional)->create(['starts_at' => $this->monday->setTime(9, 30), 'duration_minutes' => 30]);
    Booking::factory()->for($professional)->cancelled()->create(['starts_at' => $this->monday->setTime(10, 0), 'duration_minutes' => 30]);

    expect(slots($professional, $this->service, $this->monday))->toBe(['09:00', '10:00']);
});

test('applies the minimum notice for public bookings only', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 09:10'));
    $professional = bookableProfessional($this->service, [['09:00', '10:30']]);

    expect(slots($professional, $this->service, $this->monday))->toBe(['09:45', '10:00'])
        ->and(slots($professional, $this->service, $this->monday, BookingChannel::Admin))->toBe(['09:15', '09:30', '09:45', '10:00']);
});

test('rejects past dates and dates beyond the public window', function () {
    $professional = bookableProfessional($this->service);

    expect(slots($professional, $this->service, $this->monday->subWeek()))->toBe([])
        ->and(slots($professional, $this->service, $this->monday->addWeeks(5)))->toBe([])
        ->and(slots($professional, $this->service, $this->monday->addWeeks(5), BookingChannel::Admin))->not->toBe([]);
});

test('requires active records and a professional that offers the service', function () {
    $professional = bookableProfessional($this->service);
    $otherService = Service::factory()->create();

    expect(slots($professional, $otherService, $this->monday))->toBe([]);

    $this->service->update(['is_active' => false]);
    expect(slots($professional, $this->service, $this->monday))->toBe([]);

    $this->service->update(['is_active' => true]);
    $professional->update(['is_active' => false]);
    expect(slots($professional, $this->service, $this->monday))->toBe([]);
});

test('ignores the booking being edited and keeps its copied duration', function () {
    $professional = bookableProfessional($this->service, [['09:00', '10:00']]);
    $booking = Booking::factory()->for($professional)->for($this->service)->create([
        'starts_at' => $this->monday->setTime(9, 0),
        'duration_minutes' => 60,
    ]);

    expect(slots($professional, $this->service, $this->monday, BookingChannel::Admin))->toBe([])
        ->and(slots($professional, $this->service, $this->monday, BookingChannel::Admin, $booking))->toBe(['09:00']);
});
