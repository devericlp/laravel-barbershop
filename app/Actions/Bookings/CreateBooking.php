<?php

namespace App\Actions\Bookings;

use App\Data\BookingData;
use App\Enums\BookingChannel;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

final class CreateBooking
{
    public function __construct(
        private EnsureSlotIsAvailable $ensureSlotIsAvailable,
        private EnsureCustomerIsNotProfessional $ensureCustomerIsNotProfessional,
    ) {}

    public function handle(BookingData $data, BookingChannel $channel): Booking
    {
        return DB::transaction(function () use ($data, $channel) {
            $professional = Professional::lockForUpdate()->findOrFail($data->professionalId);
            $service = Service::findOrFail($data->serviceId);

            $this->ensureSlotIsAvailable->handle($professional, $service, $data->startsAt, $channel);

            $customer = Customer::findOrFail($data->customerId);

            $this->ensureCustomerIsNotProfessional->handle($customer, $professional);

            return Booking::create([
                'customer_id' => $customer->id,
                'professional_id' => $professional->id,
                'service_id' => $service->id,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->startsAt->addMinutes($service->duration_minutes),
                'price_cents' => $service->price_cents,
                'duration_minutes' => $service->duration_minutes,
                'status' => BookingStatus::Scheduled,
                'notes' => $data->notes,
            ]);
        });
    }
}
