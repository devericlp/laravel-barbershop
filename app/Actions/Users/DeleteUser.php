<?php

namespace App\Actions\Users;

use App\Actions\Professionals\DeleteProfessional;
use App\Enums\UserRole;
use App\Exceptions\CannotDeleteRecordException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeleteUser
{
    public function __construct(
        private DeleteProfessional $deleteProfessional,
    ) {}

    public function handle(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw CannotDeleteRecordException::ownAccount();
        }

        DB::transaction(function () use ($user) {
            if ($user->hasRole(UserRole::Manager->value)) {
                $this->ensureIsNotLastManager($user);
            }

            if ($user->professional !== null) {
                $this->deleteProfessional->handle($user->professional);
            }

            $user->fresh()?->delete();
        });
    }

    private function ensureIsNotLastManager(User $user): void
    {
        $managers = User::withRole(UserRole::Manager)
            ->where('tenant_id', $user->tenant_id)
            ->lockForUpdate()
            ->count();

        if ($managers <= 1) {
            throw CannotDeleteRecordException::lastManager();
        }
    }
}
