<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $customer_id
 * @property int $professional_id
 * @property int $service_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int $price_cents
 * @property int $duration_minutes
 * @property BookingStatus $status
 * @property string|null $notes
 */
#[Fillable([
    'customer_id',
    'professional_id',
    'service_id',
    'starts_at',
    'ends_at',
    'price_cents',
    'duration_minutes',
    'status',
    'notes',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'price_cents' => 'integer',
            'duration_minutes' => 'integer',
            'status' => BookingStatus::class,
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function occupying(Builder $query): void
    {
        $query->whereIn('status', BookingStatus::occupying());
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function withStatus(Builder $query, BookingStatus $status): void
    {
        $query->where('status', $status);
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function overlapping(Builder $query, CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        $query->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt);
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function startingBetween(Builder $query, CarbonImmutable $from, CarbonImmutable $until): void
    {
        $query->where('starts_at', '>=', $from)->where('starts_at', '<', $until);
    }

    /** @return Attribute<string, never> */
    protected function formattedPrice(): Attribute
    {
        return Attribute::get(fn (): string => Money::format($this->price_cents));
    }

    public function timeRange(): string
    {
        return $this->starts_at->format('H:i').' – '.$this->ends_at->format('H:i');
    }

    public function shortStart(): string
    {
        return $this->starts_at->format(__('messages.short_date_time_format'));
    }

    public function isScheduled(): bool
    {
        return $this->status === BookingStatus::Scheduled;
    }
}
