<?php

namespace App\Actions\Bookings;

use App\Actions\Customers\LinkUserAsCustomer;
use App\Data\BookingData;
use App\Enums\BookingChannel;
use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CreateCustomerBooking
{
    public function __construct(
        private LinkUserAsCustomer $linkUserAsCustomer,
        private CreateBooking $createBooking,
    ) {}

    public function handle(User $user, string $name, string $phone, int $professionalId, int $serviceId, CarbonImmutable $startsAt): Booking
    {
        return DB::transaction(function () use ($user, $name, $phone, $professionalId, $serviceId, $startsAt) {
            $customer = $user->customer()->first() ?? $this->linkUserAsCustomer->handle($user, $phone, $name);

            return $this->createBooking->handle(
                new BookingData($customer->id, $professionalId, $serviceId, $startsAt),
                BookingChannel::Public,
            );
        });
    }
}
