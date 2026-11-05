<?php

namespace App\Actions\Bookings;

use App\Actions\Customers\CreateCustomer;
use App\Data\BookingData;
use App\Enums\BookingChannel;
use App\Models\Booking;
use App\Models\Customer;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CreateGuestBooking
{
    public function __construct(
        private CreateCustomer $createCustomer,
        private CreateBooking $createBooking,
    ) {}

    public function handle(string $name, string $phone, int $professionalId, int $serviceId, CarbonImmutable $startsAt): Booking
    {
        return DB::transaction(function () use ($name, $phone, $professionalId, $serviceId, $startsAt) {
            $customer = Customer::where('phone', PhoneNumber::normalize($phone))->first()
                ?? $this->createCustomer->handle($name, $phone);

            return $this->createBooking->handle(
                new BookingData($customer->id, $professionalId, $serviceId, $startsAt),
                BookingChannel::Public,
            );
        });
    }
}
