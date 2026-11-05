<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use App\Support\PhoneNumber;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $user_id
 * @property string $name
 * @property string $phone
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'name', 'phone'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToTenant, HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScope(TenantScope::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function orderByName(Builder $query): void
    {
        $query->orderBy('name');
    }

    /** @return Attribute<string, never> */
    protected function formattedPhone(): Attribute
    {
        return Attribute::get(fn (): string => PhoneNumber::format($this->phone));
    }
}
