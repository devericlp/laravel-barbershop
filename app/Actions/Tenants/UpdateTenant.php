<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class UpdateTenant
{
    public function __construct(
        private SyncTenantRoles $syncTenantRoles,
    ) {}

    /** @param list<int> $roleIds */
    public function handle(Tenant $tenant, string $name, bool $isActive, array $roleIds): Tenant
    {
        return DB::transaction(function () use ($tenant, $name, $isActive, $roleIds) {
            $tenant->update([
                'name' => $name,
                'is_active' => $isActive,
            ]);

            $this->syncTenantRoles->handle($tenant, $roleIds);

            return $tenant;
        });
    }
}
