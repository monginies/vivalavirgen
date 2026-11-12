<?php

namespace App\Support;

class Money
{
    public static function eur(float|string|null $amount): string
    {
        return number_format((float) $amount, 2, ',', '.').' €';
    }
}
