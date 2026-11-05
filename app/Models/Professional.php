<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use Database\Factories\ProfessionalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property string|null $photo_path
 * @property bool $is_active
 * @property-read string $name
 * @property-read User $user
 */
#[Fillable(['user_id', 'photo_path', 'is_active'])]
class Professional extends Model
{
    /** @use HasFactory<ProfessionalFactory> */
    use BelongsToTenant, HasFactory;

    protected $with = ['user'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScope(TenantScope::class);
    }

    /** @return BelongsToMany<Service, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    /** @return HasMany<WorkingHour, $this> */
    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function orderByName(Builder $query): void
    {
        $query->orderBy(User::select('name')->whereColumn('users.id', 'professionals.user_id'));
    }

    public function workingDaysSummary(): string
    {
        $days = $this->workingHours->pluck('day_of_week')->unique()->sort();

        if ($days->isEmpty()) {
            return __('messages.no_working_hours');
        }

        $names = WorkingHour::dayNames();

        return $days->map(fn (int $day): string => mb_substr($names[$day], 0, 3))->implode(', ');
    }

    /** @return Attribute<string, never> */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => $this->user->name);
    }

    /** @return Attribute<string|null, never> */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->photo_path
            ? Storage::disk('public')->url($this->photo_path)
            : null);
    }
}
