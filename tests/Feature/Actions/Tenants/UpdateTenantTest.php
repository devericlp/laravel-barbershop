<?php

use App\Actions\Tenants\UpdateTenant;

test('renames and deactivates a tenant keeping its roles', function () {
    $roleIds = $this->tenant->roles()->pluck('roles.id')->all();

    $tenant = app(UpdateTenant::class)->handle($this->tenant, 'Novo Nome', false, $roleIds);

    expect($tenant->fresh()->name)->toBe('Novo Nome')
        ->and($tenant->fresh()->is_active)->toBeFalse()
        ->and($tenant->roles()->pluck('roles.id')->all())->toEqualCanonicalizing($roleIds);
});
