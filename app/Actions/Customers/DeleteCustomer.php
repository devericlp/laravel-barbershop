<?php

namespace App\Actions\Customers;

use App\Exceptions\CannotDeleteRecordException;
use App\Models\Customer;

final class DeleteCustomer
{
    public function handle(Customer $customer): void
    {
        if ($customer->user_id !== null) {
            throw CannotDeleteRecordException::customerHasAccount();
        }

        if ($customer->bookings()->exists()) {
            throw CannotDeleteRecordException::customerHasBookings();
        }

        $customer->delete();
    }
}
