<?php

namespace App\Models;

use Database\Factories\WorkingHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['day_of_week', 'starts_at', 'ends_at'])]
class WorkingHour extends Model
{
    /** @use HasFactory<WorkingHourFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    /** @return list<string> */
    public static function dayNames(): array
    {
        return array_map(
            fn (string $day): string => __('messages.'.$day),
            ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
        );
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /** @return Attribute<string, string> */
    protected function startsAt(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): string => substr($value, 0, 5),
            set: fn (string $value): string => substr($value, 0, 5).':00',
        );
    }

    /** @return Attribute<string, string> */
    protected function endsAt(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): string => substr($value, 0, 5),
            set: fn (string $value): string => substr($value, 0, 5).':00',
        );
    }
}
