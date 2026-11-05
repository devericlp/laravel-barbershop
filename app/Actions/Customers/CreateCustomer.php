<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

final class CreateCustomer
{
    public function __construct(
        private AttachUserToCustomer $attachUserToCustomer,
    ) {}

    public function handle(string $name, string $phone, ?User $user = null): Customer
    {
        return DB::transaction(function () use ($name, $phone, $user) {
            $customer = Customer::create([
                'name' => $name,
                'phone' => PhoneNumber::normalize($phone),
            ]);

            return $user !== null ? $this->attachUserToCustomer->handle($customer, $user, $name) : $customer;
        });
    }
}
