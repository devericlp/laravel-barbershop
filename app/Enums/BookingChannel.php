<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum BookingChannel
{
    case Public;
    case Admin;

    public function earliestStart(): CarbonImmutable
    {
        return match ($this) {
            self::Public => CarbonImmutable::now()->addMinutes(config()->integer('booking.min_notice_minutes')),
            self::Admin => CarbonImmutable::now(),
        };
    }

    public function lastBookableDate(): ?CarbonImmutable
    {
        return match ($this) {
            self::Public => CarbonImmutable::today()->addDays(config()->integer('booking.max_days_ahead') - 1),
            self::Admin => null,
        };
    }

    public function allowsDate(CarbonImmutable $date): bool
    {
        $lastDate = $this->lastBookableDate();

        return ! $date->startOfDay()->isBefore(CarbonImmutable::today())
            && ($lastDate === null || ! $date->startOfDay()->isAfter($lastDate));
    }
}
