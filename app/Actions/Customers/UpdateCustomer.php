<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

final class UpdateCustomer
{
    public function __construct(
        private AttachUserToCustomer $attachUserToCustomer,
        private DetachUserFromCustomer $detachUserFromCustomer,
    ) {}

    public function handle(Customer $customer, string $name, string $phone, ?User $user = null): Customer
    {
        return DB::transaction(function () use ($customer, $name, $phone, $user) {
            $linking = $user !== null && $customer->user_id !== $user->id;

            if ($customer->user_id !== null && $customer->user_id !== $user?->id) {
                $this->detachUserFromCustomer->handle($customer);
            }

            $customer->update([
                'name' => $name,
                'phone' => PhoneNumber::normalize($phone),
            ]);

            if ($linking) {
                return $this->attachUserToCustomer->handle($customer, $user, $name);
            }

            return $customer;
        });
    }
}
