<?php

namespace App\Actions\Users;

use App\Actions\Customers\LinkUserAsCustomer;
use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Exceptions\RoleChangeException;
use App\Exceptions\RoleNotEnabledException;
use App\Models\Professional;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeUserRole
{
    public const array ASSIGNABLE = [UserRole::Manager, UserRole::Professional, UserRole::Customer];

    public function __construct(
        private LinkUserAsCustomer $linkUserAsCustomer,
        private CurrentTenant $currentTenant,
    ) {}

    public function handle(User $user, UserRole $role, ?string $phone = null): User
    {
        if (! in_array($role, self::ASSIGNABLE, true)) {
            throw RoleNotEnabledException::for($role);
        }

        $tenant = $role === UserRole::Customer ? $this->currentTenant->get() : $user->tenant;

        if (! in_array($role, $user->userRoles(), true) && ! $tenant?->enablesRole($role)) {
            throw RoleNotEnabledException::for($role);
        }

        if ($role === UserRole::Customer && $phone === null && $user->customer === null) {
            throw ValidationException::withMessages(['phone' => __('messages.enter_brazilian_mobile')]);
        }

        DB::transaction(function () use ($user, $role, $phone) {
            match ($role) {
                UserRole::Manager => $user->assignRole(UserRole::Manager->value),
                UserRole::Professional => $this->ensureProfessional($user),
                default => $phone !== null ? $this->linkUserAsCustomer->handle($user, $phone) : null,
            };

            if ($role !== UserRole::Manager && $user->hasRole(UserRole::Manager->value)) {
                $this->removeManager($user);
            }

            if ($role !== UserRole::Professional && $user->hasRole(UserRole::Professional->value)) {
                $this->removeProfessional($user);
            }
        });

        return $user->load('roles');
    }

    private function ensureProfessional(User $user): void
    {
        $professional = Professional::firstOrNew(['user_id' => $user->id]);
        $professional->is_active = true;
        $professional->save();

        $user->assignRole(UserRole::Professional->value);
    }

    private function removeManager(User $user): void
    {
        $managers = User::withRole(UserRole::Manager)
            ->where('tenant_id', $user->tenant_id)
            ->lockForUpdate()
            ->count();

        if ($managers <= 1) {
            throw RoleChangeException::lastManager();
        }

        $user->removeRole(UserRole::Manager->value);
    }

    private function removeProfessional(User $user): void
    {
        $professional = $user->professional;

        if ($professional !== null) {
            $hasUpcomingBookings = $professional->bookings()
                ->withStatus(BookingStatus::Scheduled)
                ->where('starts_at', '>', now())
                ->exists();

            if ($hasUpcomingBookings) {
                throw RoleChangeException::professionalHasUpcomingBookings();
            }

            $professional->update(['is_active' => false]);
        }

        $user->removeRole(UserRole::Professional->value);
    }
}
