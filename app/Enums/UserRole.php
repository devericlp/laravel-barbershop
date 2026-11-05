<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Professional = 'professional';
    case Customer = 'customer';

    public function label(): string
    {
        return __('messages.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Admin => 'shield-exclamation',
            self::Manager => 'shield-check',
            self::Professional => 'scissors',
            self::Customer => 'calendar-days',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => __('messages.manage_platform_barbershops'),
            self::Manager => __('messages.manage_barbershop_team_services_and_schedule'),
            self::Professional => __('messages.access_your_appointment_schedule'),
            self::Customer => __('messages.book_appointment_and_track_bookings'),
        };
    }

    public function homeRoute(): string
    {
        return match ($this) {
            self::Admin => 'tenants.index',
            self::Manager => 'dashboard',
            self::Professional => 'bookings.index',
            self::Customer => 'bookings.create',
        };
    }
}
