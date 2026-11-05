<?php

namespace App\Actions\Tenants;

use App\Actions\Users\CreateUser;
use App\Enums\UserRole;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class CreateTenant
{
    public function __construct(
        private SyncTenantRoles $syncTenantRoles,
        private CreateUser $createUser,
    ) {}

    /** @param list<int> $roleIds */
    public function handle(string $name, string $managerName, string $managerEmail, string $managerPassword, array $roleIds): Tenant
    {
        return DB::transaction(function () use ($name, $managerName, $managerEmail, $managerPassword, $roleIds) {
            $tenant = Tenant::create(['name' => $name, 'slug' => Tenant::uniqueSlugFor($name), 'is_active' => true]);

            $this->syncTenantRoles->handle($tenant, $roleIds);
            $this->createUser->handle(UserRole::Manager, $managerName, $managerEmail, $managerPassword, $tenant);

            return $tenant;
        });
    }
}
