<?php

namespace App\Actions\Customers;

use App\Enums\UserRole;
use App\Exceptions\CustomerLinkException;
use App\Exceptions\RoleNotEnabledException;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AttachUserToCustomer
{
    public function handle(Customer $customer, User $user, ?string $name = null): Customer
    {
        if ($customer->user_id !== null && $customer->user_id !== $user->id) {
            throw CustomerLinkException::customerAlreadyLinked();
        }

        if ($user->customers()->where('tenant_id', $customer->tenant_id)->whereKeyNot($customer->id)->exists()) {
            throw CustomerLinkException::userAlreadyLinked();
        }

        if (! $customer->tenant?->enablesRole(UserRole::Customer)) {
            throw RoleNotEnabledException::for(UserRole::Customer);
        }

        return DB::transaction(function () use ($customer, $user, $name) {
            $customer->update([
                'user_id' => $user->id,
                'name' => $name ?? $user->name,
            ]);

            $user->assignRole(UserRole::Customer->value);

            return $customer->setRelation('user', $user);
        });
    }
}
