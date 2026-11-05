<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return __('messages.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'blue',
            self::Completed => 'green',
            self::Cancelled => 'zinc',
            self::NoShow => 'amber',
        };
    }

    /** @return list<self> */
    public static function occupying(): array
    {
        return [self::Scheduled, self::Completed];
    }
}
