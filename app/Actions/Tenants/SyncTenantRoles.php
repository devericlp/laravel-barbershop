<?php

namespace App\Actions\Tenants;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SyncTenantRoles
{
    /** @param list<int> $roleIds */
    public function handle(Tenant $tenant, array $roleIds): void
    {
        DB::transaction(function () use ($tenant, $roleIds) {
            $roleIds = Role::assignable()->whereKey($roleIds)->pluck('id')->all();

            $tenant->roles()
                ->whereKeyNot($roleIds)
                ->get()
                ->each(fn (Role $role) => $this->ensureHasNoUsers($tenant, $role));

            $tenant->roles()->sync($roleIds);
            $tenant->unsetRelation('roles');
        });
    }

    private function ensureHasNoUsers(Tenant $tenant, Role $role): void
    {
        $inUse = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->role($role)
            ->exists()
            || ($role->name === UserRole::Customer->value && $tenant->customers()->whereNotNull('user_id')->exists());

        if ($inUse) {
            throw ValidationException::withMessages([
                'roleIds' => __('messages.role_in_use_in_barbershop', ['role' => $role->label()]),
            ]);
        }
    }
}
