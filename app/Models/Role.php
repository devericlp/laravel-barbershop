<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property bool $is_system
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Role extends SpatieRole
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Tenant, $this> */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class);
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function assignable(Builder $query): void
    {
        $query->where('name', '!=', UserRole::Admin->value);
    }

    public function label(): string
    {
        return UserRole::tryFrom($this->name)?->label() ?? $this->name;
    }
}
