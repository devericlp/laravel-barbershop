<?php

namespace App\Models;

use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property bool $is_system
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Permission extends SpatiePermission
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function module(): string
    {
        return Str::before($this->name, '.');
    }
}
