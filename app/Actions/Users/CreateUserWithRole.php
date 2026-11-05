<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateUserWithRole
{
    public function __construct(
        private CreateUser $createUser,
        private ChangeUserRole $changeUserRole,
    ) {}

    public function handle(string $name, string $email, string $password, UserRole $role, ?string $phone = null): User
    {
        return DB::transaction(function () use ($name, $email, $password, $role, $phone) {
            $user = $this->createUser->handle($role, $name, $email, $password);

            return $this->changeUserRole->handle($user, $role, $phone);
        });
    }
}
