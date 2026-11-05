<?php

namespace App\Support;

final class Money
{
    public static function format(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}
