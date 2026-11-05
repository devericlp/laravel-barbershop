<?php

namespace App\Actions\Bookings;

use App\Enums\BookingChannel;
use App\Models\Booking;
use App\Models\Professional;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

final class ListAvailableSlots
{
    public function __construct(
        private ListWorkingSlots $listWorkingSlots,
    ) {}

    /** @return list<string> */
    public function handle(
        Professional $professional,
        Service $service,
        CarbonImmutable $date,
        BookingChannel $channel,
        ?Booking $ignoring = null,
    ): array {
        if (! $channel->allowsDate($date) || ! $this->canBook($professional, $service, $ignoring)) {
            return [];
        }

        $day = $date->startOfDay();
        $duration = $this->durationFor($service, $ignoring);
        $earliestStart = $channel->earliestStart();
        $occupied = $this->occupiedBookings($professional, $day, $ignoring);

        $slots = [];

        foreach ($this->listWorkingSlots->handle($professional, $duration, $day) as $start) {
            if ($start < $earliestStart || $this->overlaps($occupied, $start, $start->addMinutes($duration))) {
                continue;
            }

            $slots[] = $start->format('H:i');
        }

        return $slots;
    }

    private function canBook(Professional $professional, Service $service, ?Booking $ignoring): bool
    {
        $keepsProfessional = $ignoring?->professional_id === $professional->id;
        $keepsService = $ignoring?->service_id === $service->id;

        if (! $professional->is_active && ! $keepsProfessional) {
            return false;
        }

        if (! $service->is_active && ! $keepsService) {
            return false;
        }

        return ($keepsProfessional && $keepsService)
            || $professional->services()->whereKey($service->id)->exists();
    }

    private function durationFor(Service $service, ?Booking $ignoring): int
    {
        return $ignoring?->service_id === $service->id
            ? $ignoring->duration_minutes
            : $service->duration_minutes;
    }

    /** @return Collection<int, Booking> */
    private function occupiedBookings(Professional $professional, CarbonImmutable $day, ?Booking $ignoring): Collection
    {
        return $professional->bookings()
            ->occupying()
            ->overlapping($day, $day->addDay())
            ->when($ignoring, fn ($query) => $query->whereKeyNot($ignoring->id))
            ->get(['id', 'starts_at', 'ends_at']);
    }

    /** @param Collection<int, Booking> $occupied */
    private function overlaps(Collection $occupied, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $occupied->contains(fn (Booking $booking) => $booking->starts_at < $end && $booking->ends_at > $start);
    }
}
