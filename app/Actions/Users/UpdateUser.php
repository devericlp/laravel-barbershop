<?php

namespace App\Actions\Users;

use App\Models\User;

final class UpdateUser
{
    public function handle(User $user, string $name, string $email, ?string $password = null): User
    {
        $user->update(array_filter([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], fn (?string $value): bool => $value !== null));

        return $user;
    }
}
