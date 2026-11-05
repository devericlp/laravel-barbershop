<?php

namespace App\Actions\Bookings;

use App\Models\Customer;
use App\Models\Professional;
use Illuminate\Validation\ValidationException;

final class EnsureCustomerIsNotProfessional
{
    public function handle(Customer $customer, Professional $professional): void
    {
        if ($customer->user_id !== null && $customer->user_id === $professional->user_id) {
            throw ValidationException::withMessages([
                'professionalId' => __('messages.professional_cannot_book_with_themselves'),
            ]);
        }
    }
}
