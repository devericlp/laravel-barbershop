<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Scopes\TenantScope;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 * @property-read Collection<int, Role> $roles
 */
#[Fillable(['name', 'slug', 'is_active'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    public static function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name) ?: 'barbershop';
        $slug = $base;
        $suffix = 2;

        while (self::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class)->withoutGlobalScope(TenantScope::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function enablesRole(UserRole $role): bool
    {
        return $this->roles->contains('name', $role->value);
    }

    public function bookingUrl(): string
    {
        return route('public-bookings.create', ['barbershop' => $this->slug]);
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function acceptingCustomers(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', UserRole::Customer->value));
    }
}
