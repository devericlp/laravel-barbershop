<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureRoleIsSelected;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use App\Support\CurrentTenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $avatar_path
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant|null $tenant
 * @property-read Professional|null $professional
 * @property-read Customer|null $customer
 * @property-read Collection<int, Customer> $customers
 */
#[Fillable(['name', 'email', 'password', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return HasOne<Professional, $this> */
    public function professional(): HasOne
    {
        return $this->hasOne(Professional::class);
    }

    /** @return HasOne<Customer, $this> */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class)->where('customers.tenant_id', app(CurrentTenant::class)->id());
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class)->withoutGlobalScope(TenantScope::class);
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function withRole(Builder $query, UserRole $role): void
    {
        $query->role($role->value);
    }

    /** @return list<UserRole> */
    public function userRoles(): array
    {
        $names = $this->roles->pluck('name')->all();

        return array_values(array_filter(UserRole::cases(), fn (UserRole $role) => in_array($role->value, $names, true)));
    }

    public function mainRole(): ?UserRole
    {
        $roles = $this->userRoles();

        return array_values(array_filter($roles, fn (UserRole $role) => $role !== UserRole::Customer))[0] ?? $roles[0] ?? null;
    }

    public function isLinkedCustomer(): bool
    {
        return $this->mainRole() !== UserRole::Customer && in_array(UserRole::Customer, $this->userRoles(), true);
    }

    public function canBeLinkedAsCustomer(): bool
    {
        $mainRole = $this->mainRole();

        return $mainRole !== null && $mainRole !== UserRole::Customer && ! $this->isLinkedCustomer();
    }

    public function activeRole(): ?UserRole
    {
        $roles = $this->userRoles();

        if (count($roles) === 1) {
            return $roles[0];
        }

        $selected = UserRole::tryFrom((string) session(EnsureRoleIsSelected::SESSION_KEY));

        return in_array($selected, $roles, true) ? $selected : null;
    }

    public function hasMultipleRoles(): bool
    {
        return count($this->userRoles()) > 1;
    }

    public function needsRoleSelection(): bool
    {
        return $this->activeRole() === null && $this->hasMultipleRoles();
    }

    public function hasUserRole(UserRole ...$roles): bool
    {
        return in_array($this->activeRole(), $roles, true);
    }

    public function activeRolePermits(string $ability): ?bool
    {
        if (app(PermissionRegistrar::class)->getPermissions(['name' => $ability])->isEmpty()) {
            return null;
        }

        $role = $this->activeRole();

        $activeRole = $this->roles->first(fn (Model $model) => $model->getAttribute('name') === $role?->value);

        return $activeRole instanceof Role && $activeRole->hasPermissionTo($ability);
    }

    public const array TENANT_HOME_ROUTES = [
        'dashboard' => 'dashboard.index',
        'bookings.index' => 'bookings.index',
        'bookings.create' => 'my-bookings.create',
        'my-bookings.index' => 'my-bookings.index',
        'services.index' => 'services.index',
        'professionals.index' => 'professionals.index',
        'customers.index' => 'customers.index',
        'users.index' => 'users.index',
    ];

    public function viewsPlatform(): bool
    {
        return $this->hasUserRole(UserRole::Admin) && app(CurrentTenant::class)->get() === null;
    }

    public function barbershopBookingUrl(): ?string
    {
        $tenant = app(CurrentTenant::class)->get();

        return $tenant !== null && $this->hasUserRole(UserRole::Manager, UserRole::Admin) ? $tenant->bookingUrl() : null;
    }

    public function homeUrl(): string
    {
        if ($this->needsRoleSelection()) {
            return route('select-role');
        }

        $role = $this->activeRole();

        if ($role !== null && $this->viewsPlatform()) {
            return route($role->homeRoute());
        }

        $preferred = $role === UserRole::Admin ? 'dashboard' : $role?->homeRoute();

        $permitted = array_filter(self::TENANT_HOME_ROUTES, fn (string $permission) => $this->can($permission));

        if ($preferred !== null && array_key_exists($preferred, $permitted)) {
            return route($preferred);
        }

        return route(array_key_first($permitted) ?? 'profile.edit');
    }

    /** @return Attribute<string|null, never> */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : null);
    }

    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
