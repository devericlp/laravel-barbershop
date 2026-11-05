<?php

namespace App\Support;

final class PhoneNumber
{
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) === 13 && str_starts_with($digits, '55')) {
            return substr($digits, 2);
        }

        return $digits;
    }

    public static function isValidMobile(string $phone): bool
    {
        return preg_match('/^[1-9]{2}9\d{8}$/', self::normalize($phone)) === 1;
    }

    public static function format(string $phone): string
    {
        $digits = self::normalize($phone);

        if (strlen($digits) !== 11) {
            return $phone;
        }

        return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7));
    }
}
