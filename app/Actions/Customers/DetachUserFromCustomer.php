<?php

namespace App\Actions\Customers;

use App\Enums\UserRole;
use App\Exceptions\RoleChangeException;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

final class DetachUserFromCustomer
{
    public function handle(Customer $customer): Customer
    {
        $user = $customer->user;

        if ($user === null) {
            return $customer;
        }

        $keepsCustomerRole = $user->customers()->whereKeyNot($customer->id)->exists();

        if (! $keepsCustomerRole && $user->userRoles() === [UserRole::Customer]) {
            throw RoleChangeException::userNeedsOneRole();
        }

        return DB::transaction(function () use ($customer, $user, $keepsCustomerRole) {
            $customer->update(['user_id' => null]);

            if (! $keepsCustomerRole) {
                $user->removeRole(UserRole::Customer->value);
            }

            return $customer->setRelation('user', null);
        });
    }
}
