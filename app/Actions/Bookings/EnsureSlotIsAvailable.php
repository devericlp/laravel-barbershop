<?php

namespace App\Actions\Bookings;

use App\Enums\BookingChannel;
use App\Models\Booking;
use App\Models\Professional;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class EnsureSlotIsAvailable
{
    public function __construct(
        private ListAvailableSlots $listAvailableSlots,
    ) {}

    public function handle(
        Professional $professional,
        Service $service,
        CarbonImmutable $startsAt,
        BookingChannel $channel,
        ?Booking $ignoring = null,
    ): void {
        $slots = $this->listAvailableSlots->handle($professional, $service, $startsAt, $channel, $ignoring);

        if (! in_array($startsAt->format('H:i'), $slots, true)) {
            throw ValidationException::withMessages([
                'time' => __('messages.time_not_available'),
            ]);
        }
    }
}
