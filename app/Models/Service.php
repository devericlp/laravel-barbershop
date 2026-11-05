<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property string|null $description
 * @property string|null $image_path
 * @property-read string|null $image_url
 */
#[Fillable(['name', 'description', 'image_path', 'price_cents', 'duration_minutes', 'is_active'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'duration_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Professional, $this> */
    public function professionals(): BelongsToMany
    {
        return $this->belongsToMany(Professional::class);
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

    /** @return Attribute<string, never> */
    protected function formattedPrice(): Attribute
    {
        return Attribute::get(fn (): string => Money::format($this->price_cents));
    }

    /** @return Attribute<string|null, never> */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->image_path
            ? Storage::disk('public')->url($this->image_path)
            : null);
    }

    public function durationLabel(): string
    {
        return __('messages.count_minutes', ['count' => $this->duration_minutes]);
    }
}
