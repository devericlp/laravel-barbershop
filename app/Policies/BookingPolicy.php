<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function create(User $user): bool
    {
        return $user->can('bookings.create');
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->can('bookings.update') && $this->reaches($user, $booking);
    }

    public function complete(User $user, Booking $booking): bool
    {
        return $user->can('bookings.complete') && $this->reaches($user, $booking);
    }

    public function markAsNoShow(User $user, Booking $booking): bool
    {
        return $user->can('bookings.no-show') && $this->reaches($user, $booking);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $user->can('bookings.cancel') && $this->reaches($user, $booking);
    }

    public function cancelOwn(User $user, Booking $booking): bool
    {
        return $user->can('my-bookings.cancel') && $user->hasUserRole(UserRole::Customer) && $user->customers()->whereKey($booking->customer_id)->exists();
    }

    private function reaches(User $user, Booking $booking): bool
    {
        return ! $user->hasUserRole(UserRole::Professional) || $booking->professional_id === $user->professional?->id;
    }
}
