<?php

namespace App\Actions\Bookings;

use App\Data\BookingData;
use App\Enums\BookingChannel;
use App\Exceptions\BookingNotScheduledException;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

final class UpdateBooking
{
    public function __construct(
        private EnsureSlotIsAvailable $ensureSlotIsAvailable,
        private EnsureCustomerIsNotProfessional $ensureCustomerIsNotProfessional,
    ) {}

    public function handle(Booking $booking, BookingData $data): Booking
    {
        if (! $booking->isScheduled()) {
            throw new BookingNotScheduledException;
        }

        return DB::transaction(function () use ($booking, $data) {
            $professional = Professional::lockForUpdate()->findOrFail($data->professionalId);
            $service = Service::findOrFail($data->serviceId);

            if (! $this->keepsSlot($booking, $data)) {
                $this->ensureSlotIsAvailable->handle($professional, $service, $data->startsAt, BookingChannel::Admin, $booking);
            }

            $customer = Customer::findOrFail($data->customerId);

            $this->ensureCustomerIsNotProfessional->handle($customer, $professional);

            $keepsService = $booking->service_id === $service->id;
            $priceCents = $keepsService ? $booking->price_cents : $service->price_cents;
            $durationMinutes = $keepsService ? $booking->duration_minutes : $service->duration_minutes;

            $booking->update([
                'customer_id' => $customer->id,
                'professional_id' => $professional->id,
                'service_id' => $service->id,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->startsAt->addMinutes($durationMinutes),
                'price_cents' => $priceCents,
                'duration_minutes' => $durationMinutes,
                'notes' => $data->notes,
            ]);

            return $booking;
        });
    }

    private function keepsSlot(Booking $booking, BookingData $data): bool
    {
        return $booking->professional_id === $data->professionalId
            && $booking->service_id === $data->serviceId
            && $booking->starts_at->equalTo($data->startsAt);
    }
}
