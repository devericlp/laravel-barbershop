<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateUserWithRole
{
    public function __construct(
        private UpdateUser $updateUser,
        private ChangeUserRole $changeUserRole,
    ) {}

    public function handle(User $user, string $name, string $email, ?string $password, UserRole $role, ?string $phone = null): User
    {
        return DB::transaction(function () use ($user, $name, $email, $password, $role, $phone) {
            $this->updateUser->handle($user, $name, $email, $password);

            return $this->changeUserRole->handle($user, $role, $phone);
        });
    }
}
