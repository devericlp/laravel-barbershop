<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Validation\ValidationException;

final class LinkUserAsCustomer
{
    public function __construct(
        private CreateCustomer $createCustomer,
        private UpdateCustomer $updateCustomer,
        private AttachUserToCustomer $attachUserToCustomer,
    ) {}

    public function handle(User $user, string $phone, ?string $name = null): Customer
    {
        $name ??= $user->name;
        $customer = $user->customer()->first();
        $existing = Customer::where('phone', PhoneNumber::normalize($phone))->first();

        if ($existing !== null && ! $existing->is($customer) && ($customer !== null || $existing->user_id !== null)) {
            throw ValidationException::withMessages(['phone' => __('messages.customer_phone_already_exists')]);
        }

        $linked = match (true) {
            $customer !== null => $this->updateCustomer->handle($customer, $name, $phone, $user),
            $existing !== null => $this->attachUserToCustomer->handle($existing, $user, $name),
            default => $this->createCustomer->handle($name, $phone, $user),
        };

        $user->setRelation('customer', $linked);

        return $linked;
    }
}
